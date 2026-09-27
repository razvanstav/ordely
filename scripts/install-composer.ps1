$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskTools = Join-Path $taskRoot 'var/tools'
New-Item -ItemType Directory -Force -Path $taskTools | Out-Null
$taskInstaller = Join-Path $taskTools 'composer-setup.php'
Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile $taskInstaller
$taskExpected = (Invoke-RestMethod -Uri 'https://composer.github.io/installer.sig').Trim()
$taskActual = (Get-FileHash -LiteralPath $taskInstaller -Algorithm SHA384).Hash.ToLowerInvariant()
if ($taskExpected -ne $taskActual) { throw 'Composer installer checksum mismatch; installer was not executed.' }
php $taskInstaller "--install-dir=$taskTools" --filename=composer.phar --version=2.10.3 --quiet
if ($LASTEXITCODE -ne 0) { throw 'Composer installation failed.' }
php (Join-Path $taskTools 'composer.phar') --version
if ($LASTEXITCODE -ne 0) { throw 'Composer verification failed.' }
