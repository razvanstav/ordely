param([ValidateSet('Start', 'Stop')][string]$Action = 'Start')

$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskVersion = '8.4.11'
$taskTools = Join-Path $taskRoot 'var/tools'
$taskRuntime = Join-Path $taskRoot 'var/mysql'
$taskBase = Join-Path $taskTools "mysql-$taskVersion-winx64"
$taskServer = Join-Path $taskBase 'bin/mysqld.exe'
$taskAdmin = Join-Path $taskBase 'bin/mysqladmin.exe'
$taskClientConfig = Join-Path $taskRuntime 'client.ini'

if ($Action -eq 'Stop') {
    if (-not (Test-Path -LiteralPath $taskClientConfig)) { throw 'No project MySQL configuration exists.' }
    & $taskAdmin "--defaults-extra-file=$taskClientConfig" shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Could not shut down the project MySQL instance.' }
    Write-Output 'Project MySQL stopped; data preserved in var/mysql/data.'
    exit 0
}

New-Item -ItemType Directory -Force -Path $taskTools, $taskRuntime | Out-Null
if (-not (Test-Path -LiteralPath $taskServer)) {
    $taskArchive = Join-Path $taskTools "mysql-$taskVersion-winx64.zip"
    if (-not (Test-Path -LiteralPath $taskArchive)) {
        Invoke-WebRequest -Uri "https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-$taskVersion-winx64.zip" -OutFile $taskArchive
    }
    Expand-Archive -LiteralPath $taskArchive -DestinationPath $taskTools -Force
}
$taskSignature = Get-AuthenticodeSignature -LiteralPath $taskServer
if ($taskSignature.Status -ne 'Valid' -or $taskSignature.SignerCertificate.Subject -notmatch 'Oracle') {
    throw 'The MySQL executable must have a valid Oracle signature.'
}

$taskEnvPath = Join-Path $taskRoot '.env'
if (-not (Test-Path -LiteralPath $taskEnvPath)) {
    $taskExample = Get-Content -LiteralPath (Join-Path $taskRoot '.env.example') -Raw
    $taskExample = $taskExample.Replace('ordely_local_only', [guid]::NewGuid().ToString('N'))
    $taskExample = $taskExample.Replace('root_local_only', [guid]::NewGuid().ToString('N'))
    [IO.File]::WriteAllText($taskEnvPath, $taskExample)
}

# This helper deliberately accepts only the simple local development profile.
# Custom servers/credentials should use the native setup described in docs/setup.md.
$taskEnv = @{}
foreach ($taskLine in Get-Content -LiteralPath $taskEnvPath) {
    if ($taskLine -match '^([A-Z_]+)=([^\r\n]*)$') { $taskEnv[$Matches[1]] = $Matches[2] }
}
if ($taskEnv['DB_HOST'] -ne '127.0.0.1' -or $taskEnv['DB_PORT'] -ne '33060' -or
    $taskEnv['DB_NAME'] -ne 'ordely' -or $taskEnv['DB_TEST_NAME'] -ne 'ordely_test' -or $taskEnv['DB_USER'] -ne 'ordely') {
    throw 'The portable MySQL helper requires the default local host/port/database/user profile. Existing .env was preserved.'
}
foreach ($taskSecretName in @('DB_PASSWORD', 'MYSQL_ROOT_PASSWORD')) {
    if ($taskEnv[$taskSecretName] -notmatch '^[A-Za-z0-9_-]{12,128}$') {
        throw 'Portable local MySQL passwords must contain 12-128 unquoted letters, digits, underscores or hyphens.'
    }
}

if (Test-Path -LiteralPath $taskClientConfig) {
    & $taskAdmin "--defaults-extra-file=$taskClientConfig" ping 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Output 'Project MySQL is already running on 127.0.0.1:33060.'; exit 0 }
}
$taskPortProbe = [Net.Sockets.TcpClient]::new()
try {
    $taskPortProbe.Connect('127.0.0.1', 33060)
    throw 'Port 33060 is already occupied by another service; no server was changed.'
} catch [Net.Sockets.SocketException] {
    # No listener: safe to start this project's instance.
} finally { $taskPortProbe.Dispose() }

$taskData = Join-Path $taskRuntime 'data'
if (-not (Test-Path -LiteralPath (Join-Path $taskData 'mysql'))) {
    & $taskServer --no-defaults --initialize-insecure "--basedir=$taskBase" "--datadir=$taskData"
    if ($LASTEXITCODE -ne 0) { throw 'MySQL initialization failed; existing files were preserved.' }
}

$taskServerConfig = Join-Path $taskRuntime 'my.ini'
$taskInitFile = Join-Path $taskRuntime 'bootstrap.sql'
$taskRootPassword = $taskEnv['MYSQL_ROOT_PASSWORD']
$taskAppPassword = $taskEnv['DB_PASSWORD']
$taskSql = @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$taskRootPassword';
CREATE DATABASE IF NOT EXISTS ordely CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS ordely_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS 'ordely'@'127.0.0.1' IDENTIFIED BY '$taskAppPassword';
ALTER USER 'ordely'@'127.0.0.1' IDENTIFIED BY '$taskAppPassword';
GRANT ALL PRIVILEGES ON ordely.* TO 'ordely'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ordely_test.* TO 'ordely'@'127.0.0.1';
"@
[IO.File]::WriteAllText($taskInitFile, $taskSql)
$taskIni = @"
[mysqld]
basedir="$($taskBase.Replace('\','/'))"
datadir="$($taskData.Replace('\','/'))"
bind-address=127.0.0.1
port=33060
mysqlx=0
skip-name-resolve
skip-log-bin
character-set-server=utf8mb4
collation-server=utf8mb4_0900_ai_ci
default-time-zone=+00:00
log-error="$($taskRuntime.Replace('\','/'))/mysql-error.log"
init-file="$($taskInitFile.Replace('\','/'))"
"@
[IO.File]::WriteAllText($taskServerConfig, $taskIni)
$taskClientIni = @"
[client]
host=127.0.0.1
port=33060
user=root
password=$taskRootPassword
"@
[IO.File]::WriteAllText($taskClientConfig, $taskClientIni)

Start-Process -FilePath $taskServer -ArgumentList "--defaults-file=`"$taskServerConfig`"" -WindowStyle Hidden | Out-Null
for ($taskAttempt = 0; $taskAttempt -lt 30; $taskAttempt++) {
    Start-Sleep -Seconds 1
    & $taskAdmin "--defaults-extra-file=$taskClientConfig" ping 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Output "MySQL $taskVersion ready on 127.0.0.1:33060. Local credentials remain in ignored files."
        exit 0
    }
}
throw 'MySQL did not become ready. Inspect var/mysql/mysql-error.log; data was preserved.'
