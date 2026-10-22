$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$localPhp = Join-Path $env:LOCALAPPDATA "AlumniConnect\php-8.5.11\php.exe"
$xamppPhp = "C:\xampp\php\php.exe"
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$runtimeArguments = @()

if (Test-Path -LiteralPath $localPhp) {
    $phpPath = $localPhp
    $extensionDirectory = Join-Path (Split-Path -Parent $localPhp) "ext"
    $pdoMysqlExtension = Join-Path $extensionDirectory "php_pdo_mysql.dll"
    if (Test-Path -LiteralPath $pdoMysqlExtension) {
        $runtimeArguments = @("-d", "extension_dir=$extensionDirectory", "-d", "extension=php_pdo_mysql.dll")
    } else {
        throw "PDO MySQL is missing from the downloaded PHP runtime."
    }
} elseif (Test-Path -LiteralPath $xamppPhp) {
    $phpPath = $xamppPhp
} elseif ($phpCommand) {
    $phpPath = $phpCommand.Source
} else {
    throw "PHP 8+ was not found. Install PHP or use the runtime in the README instructions."
}

$phpVersionId = & $phpPath @runtimeArguments -r "echo PHP_VERSION_ID;"
$phpVersionNumber = 0
if ($LASTEXITCODE -ne 0 -or -not [int]::TryParse(($phpVersionId -join ""), [ref]$phpVersionNumber) -or $phpVersionNumber -lt 80000) {
    throw "The selected PHP runtime must be PHP 8 or newer."
}

$phpModules = & $phpPath @runtimeArguments -m
if ($LASTEXITCODE -ne 0 -or $phpModules -notcontains "pdo_mysql") {
    throw "PDO MySQL is not enabled in the selected PHP runtime."
}

$phpArguments = $runtimeArguments + @(
    "-S"
    "127.0.0.1:8000"
    "-t"
    (Join-Path $projectRoot "public")
    (Join-Path $projectRoot "public\router.php")
)

Write-Output "Serving AlumniConnect at http://127.0.0.1:8000/"
Write-Output "Using PHP: $phpPath"
Write-Output "Press Ctrl+C to stop the development server."

& $phpPath @phpArguments
