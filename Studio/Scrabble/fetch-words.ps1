# Downloads the public-domain ENABLE word list (~173k words) for Scrabble validation.
# Run from repo root: powershell -File Studio/Scrabble/fetch-words.ps1
$dest = Join-Path $PSScriptRoot "enable.txt"
$uri = "https://raw.githubusercontent.com/dolph/dictionary/master/enable1.txt"
Write-Host "Downloading to $dest ..."
Invoke-WebRequest -Uri $uri -OutFile $dest -UseBasicParsing
Write-Host "Done. Lines:" (Get-Content $dest | Measure-Object -Line).Lines
