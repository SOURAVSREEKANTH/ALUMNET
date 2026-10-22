$ErrorActionPreference = "Stop"

$mysqlBaseDirectory = "C:\Program Files\MySQL\MySQL Server 8.0"
$mysqlExecutable = Join-Path $mysqlBaseDirectory "bin\mysqld.exe"
$dataDirectory = Join-Path $env:LOCALAPPDATA "AlumniConnect\mysql-data"
$databasePort = 3307

if (-not (Test-Path -LiteralPath $mysqlExecutable)) {
    throw "MySQL Server 8 is not installed at the expected local path: $mysqlExecutable"
}

if (-not (Test-Path -LiteralPath $dataDirectory -PathType Container)) {
    throw "The isolated AlumniConnect data directory was not found: $dataDirectory"
}

if (Test-NetConnection -ComputerName "127.0.0.1" -Port $databasePort -InformationLevel Quiet -WarningAction SilentlyContinue) {
    throw "Port $databasePort is already accepting connections. Check whether the local MySQL server is already running."
}

Write-Output "Starting the isolated AlumniConnect database on 127.0.0.1:$databasePort"
Write-Output "Data directory: $dataDirectory"
Write-Output "Keep this window open while using the application. Press Ctrl+C to stop MySQL."

& $mysqlExecutable `
    "--basedir=$mysqlBaseDirectory" `
    "--datadir=$dataDirectory" `
    "--bind-address=127.0.0.1" `
    "--port=$databasePort" `
    "--mysqlx=0" `
    "--console"
