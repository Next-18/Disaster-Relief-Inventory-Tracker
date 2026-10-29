$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpPath = (Get-Command php.exe -ErrorAction Stop).Source
$artisanPath = Join-Path $projectRoot 'artisan'

$process = Start-Process `
    -FilePath $phpPath `
    -ArgumentList @("`"$artisanPath`"", 'schedule:run') `
    -WorkingDirectory $projectRoot `
    -WindowStyle Hidden `
    -Wait `
    -PassThru

exit $process.ExitCode
