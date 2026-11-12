param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$WpArgs
)

$ErrorActionPreference = 'Stop'
$SiteRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..\..\..')
$LocalPhp = "$env:APPDATA\Local\lightning-services\php-8.5.1+1\bin\win64\php.exe"
$PhpIni   = "$env:APPDATA\Local\run\OuGjEZoDx\conf\php\php.ini"
$WpPhar   = "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar"

if (-not (Test-Path $LocalPhp)) {
    throw "Local PHP binary not found at: $LocalPhp"
}
if (-not (Test-Path $WpPhar)) {
    throw "WP-CLI phar not found at: $WpPhar"
}

& $LocalPhp -c $PhpIni $WpPhar --path="$SiteRoot" @WpArgs
exit $LASTEXITCODE

