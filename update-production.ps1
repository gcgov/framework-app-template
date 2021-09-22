function Menu ($object, $prompt) {
    if (!$object) { Throw 'Must provide an object.' }
    $ok = $false
    Write-Host ''
    do {
        if ($prompt) { Write-Host $prompt }
        for ($i = 0; $i -lt $object.count; $i++) {
            Write-Host $i`. $object[$i]
        }
        Write-Host ''
        $answer = Read-Host
        if ($answer -in 0..($object.count-1)) {
            $object[$answer]
            $ok = $true
        } else {
            Write-Host 'Not an option!' -ForegroundColor Red
            Write-Host ''
        }
    } while (!$ok)
}

Write-Host "git pull"
git pull

Write-Host "`nMost Recent Tags:"
$recentTags = git tag --sort=version:refname | select -Last 5

$tag = menu -object $recentTags -prompt 'Which tag do you want to check out?'

Write-Host "git checkout tags/$tag"
git checkout tags/$tag

Write-Host "git submodule sync"
git submodule sync

Write-Host "git submodule update"
git submodule update

Write-Host "update environment files"
Copy-Item -Path app/config/environment-prod.json -Destination app/config/environment.json
Copy-Item -Path composer-prod.json -Destination composer.json
Copy-Item -Path www/web-prod.config -Destination www/web.config

#UPDATE VERSION
Write-Host "Set versions"
$status = git status | Select -first 1
$statusWords = -split $status
$apiVersion = $statusWords[-1]
Write-Host "Version: $apiVersion"

$jsonBase = @{}
$jsonBase.Add("version",$apiVersion)
$jsonBase.Add("inherit",$true)
$jsonBase | ConvertTo-Json | Out-File "version.json"

Write-Host "composer update"
composer update