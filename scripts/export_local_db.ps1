# Exporta o banco local (XAMPP) para SQL — rode no PowerShell:
#   cd c:\xampp\htdocs\producao\votacao\scripts
#   .\export_local_db.ps1

$ErrorActionPreference = 'Stop'
$MysqlBin = 'C:\xampp\mysql\bin\mysqldump.exe'
$DbHost = '127.0.0.1'
$Port = '3307'
$User = 'root'
$Pass = ''   # senha do root no XAMPP (vazio por padrao)
$Database = 'plenario'
$OutFile = Join-Path $PSScriptRoot 'plenario_local_export.sql'

if (-not (Test-Path $MysqlBin)) {
    Write-Error "mysqldump nao encontrado em $MysqlBin. Ajuste `$MysqlBin no script."
}

$dumpArgs = @(
    "--host=$DbHost",
    "--port=$Port",
    "--user=$User",
    "--default-character-set=utf8mb4",
    "--single-transaction",
    "--routines",
    "--triggers",
    $Database
)
if ($Pass -ne '') {
    $dumpArgs = @("--password=$Pass") + $dumpArgs
}

Write-Host "Exportando $Database -> $OutFile"
& $MysqlBin @dumpArgs | Out-File -FilePath $OutFile -Encoding utf8
Write-Host "OK. Envie plenario_local_export.sql ao servidor e rode: bash scripts/import_on_server.sh plenario_local_export.sql"
