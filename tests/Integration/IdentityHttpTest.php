<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;

use Ordely\Identity\Infrastructure\Provisioner;
use Ordely\Infrastructure\Database\{ConnectionFactory, DatabaseConfig, Migrator, Sql};
use Ordely\Shared\Id;
use Ordely\Tests\Support\FixtureCleanup;
use PHPUnit\Framework\TestCase;

final class IdentityHttpTest extends TestCase
{
    /** @param array<string,mixed>|null $data
     * @return array{status:int,headers:list<string>,body:string} */
    private function request(string $url, string $method = 'GET', ?array $data = null, string $cookie = '', string $csrf = ''): array
    {
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 5,
            'header' => ['Content-Type: application/json', 'Cookie: ' . $cookie, 'X-CSRF-Token: ' . $csrf,'Idempotency-Key: '.Id::new()],
            'content' => $data === null ? '' : json_encode((object) $data, JSON_THROW_ON_ERROR)]]);
        $body = file_get_contents($url, false, $context);
        self::assertIsString($body);
        $headers = $http_response_header;
        preg_match('/HTTP\/\S+ (\d+)/', $headers[0] ?? '', $match);
        return ['status' => (int) ($match[1] ?? 0), 'headers' => $headers, 'body' => $body];
    }

    public function testBrowserHttpFlowAgainstSeparatePhpServerAndMysql(): void
    {
        $db = new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(true)))->connect());
        (new Migrator($db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
        $email = Id::new() . '@example.test'; $password = bin2hex(random_bytes(16));
        $identity = (new Provisioner($db))->create('HTTP smoke merchant', $email, $password);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false); self::assertIsString($address); fclose($socket);
        $base = 'http://' . $address;
        $environment = array_merge(getenv(), ['APP_ENV' => 'test', 'DB_NAME' => DatabaseConfig::fromEnvironment(true)->database]);
        unset($environment['SYMFONY_DOTENV_VARS'], $environment['SYMFONY_DOTENV_PATH']);
        $root = dirname(__DIR__, 2); $log = $root . '/var/http-test-' . Id::new() . '.log';
        $keyFile=$root.'/var/http-key-'.Id::new().'.json';$testKey=base64_encode(random_bytes(32));
        file_put_contents($keyFile,json_encode(['active'=>'http-test','keys'=>['http-test'=>$testKey]],JSON_THROW_ON_ERROR));$environment['ORDELY_KEYRING_FILE']=$keyFile;
        $process = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/public', $root . '/public/index.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $environment, ['bypass_shell' => true]);
        try {
            self::assertIsResource($process); fclose($pipes[0]);
            $ready = false;
            for ($attempt = 0; $attempt < 60; ++$attempt) {
                $connection = @stream_socket_client('tcp://' . $address, timeout: 0.1);
                if (is_resource($connection)) { fclose($connection); $ready = true; break; }
                usleep(50000);
            }
            self::assertTrue($ready, 'Test HTTP server did not start.');
            $home = $this->request($base . '/'); self::assertSame(200, $home['status']); self::assertStringContainsString('Magazinele tale', $home['body']);
            self::assertSame(200, $this->request($base . '/app.js')['status']);
            $login = $this->request($base . '/api/auth/login', 'POST', ['email' => $email, 'password' => $password]);
            self::assertSame(200, $login['status'], 'HTTP login failed; server log: ' . file_get_contents($log));
            $cookie = '';
            foreach ($login['headers'] as $header) { if (str_starts_with(strtolower($header), 'set-cookie: ordely_session=')) { $cookie = explode(';', substr($header, 12))[0]; } }
            self::assertStringStartsWith('ordely_session=', $cookie);
            $body = json_decode($login['body'], true, flags: JSON_THROW_ON_ERROR);
            $csrf = $body['csrf']; self::assertIsString($csrf);
            $created = $this->request($base . '/api/stores', 'POST', ['name' => 'Magazin 🧵', 'platform' => 'manual'], $cookie, $csrf);
            self::assertSame(201, $created['status']);
            $stores = $this->request($base . '/api/stores', cookie: $cookie);
            self::assertSame(200, $stores['status']);
            self::assertStringContainsString('Magazin', $stores['body']);
            $storeId=json_decode($created['body'],true,flags:JSON_THROW_ON_ERROR)['id'];$connectionId=Id::new();$credential='SYNTHETIC-REAL-HTTP-TOKEN';
            $connection=$this->request($base.'/api/integrations','POST',['id'=>$connectionId,'provider'=>'fake-carrier','label'=>'HTTP carrier','credentials'=>['apiToken'=>$credential]],$cookie,$csrf);
            self::assertSame(201,$connection['status']);
            self::assertSame(200,$this->request($base.'/api/integrations/'.$connectionId.'/bind','POST',['storeId'=>$storeId,'version'=>1,'isDefault'=>true],$cookie,$csrf)['status']);
            self::assertSame(200,$this->request($base.'/api/integrations/'.$connectionId.'/capabilities','POST',['storeId'=>$storeId,'kind'=>'carrier'],$cookie,$csrf)['status']);
            $connections=$this->request($base.'/api/integrations',cookie:$cookie);self::assertSame(200,$connections['status']);self::assertStringNotContainsString($credential,$connections['body']);self::assertStringNotContainsString($testKey,$connections['body']);
            // Force an authenticated-decryption failure and verify the public error and log stay redacted.
            $db->run("UPDATE provider_connections SET credentials_envelope=JSON_SET(credentials_envelope,'$.tag',?) WHERE id=?",[base64_encode(str_repeat('x',16)),Id::bytes($connectionId)]);
            $failure=$this->request($base.'/api/integrations/'.$connectionId.'/capabilities','POST',['storeId'=>$storeId,'kind'=>'carrier'],$cookie,$csrf);
            self::assertSame(500,$failure['status']);self::assertStringNotContainsString($credential,$failure['body']);
            self::assertSame(200,$this->request($base.'/api/integrations/'.$connectionId.'/revoke','POST',['version'=>2],$cookie,$csrf)['status']);
            self::assertSame(403,$this->request($base.'/api/integrations/'.$connectionId.'/capabilities','POST',['storeId'=>$storeId,'kind'=>'carrier'],$cookie,$csrf)['status']);
            self::assertSame(200, $this->request($base . '/api/auth/logout', 'POST', [], $cookie, $csrf)['status']);
            self::assertSame(401, $this->request($base . '/api/stores', cookie: $cookie)['status']);
            self::assertSame(404, $this->request($base . '/.env')['status']);
            $logged = file_get_contents($log); self::assertIsString($logged);
            self::assertStringNotContainsString($password, $logged); self::assertStringNotContainsString($cookie, $logged);
            self::assertStringNotContainsString($credential,$logged);self::assertStringNotContainsString($testKey,$logged);
        } finally {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            FixtureCleanup::merchant($db,$identity['merchantId'],$identity['userId']);
            $db->run('DELETE FROM auth_rate_limits WHERE bucket IN (?,?)', [hash('sha256', 'account:' . $email, true), hash('sha256', 'ip:127.0.0.1', true)]);
            if (is_file($log)) { unlink($log); }
            if(is_file($keyFile)){unlink($keyFile);}
        }
    }
}
