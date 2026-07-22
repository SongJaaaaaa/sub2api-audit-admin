param(
    [string]$Git = 'D:\Git\cmd\git.exe',
    [string]$Output = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$tmpRoot = Join-Path $root 'release-tmp'
$stage = Join-Path $tmpRoot "production-$PID"
$source = Join-Path $tmpRoot "source-$PID.zip"
$tmpPrefix = [System.IO.Path]::GetFullPath($tmpRoot).TrimEnd('\') + '\'
$stagePath = [System.IO.Path]::GetFullPath($stage)

if (-not (Test-Path -LiteralPath $Git -PathType Leaf)) {
    throw "Git not found: $Git"
}
if (-not (Test-Path -LiteralPath (Join-Path $root 'frontend\dist\index.html') -PathType Leaf)) {
    throw 'frontend/dist is missing. Run npm run build first.'
}
if (Test-Path -LiteralPath $stage) {
    throw "Temporary directory already exists: $stage"
}
if (-not $stagePath.StartsWith($tmpPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Temporary directory is outside release-tmp: $stagePath"
}

if ($Output -eq '') {
    $Output = Join-Path $tmpRoot 'sub2api-audit-admin-production.zip'
} elseif (-not [System.IO.Path]::IsPathRooted($Output)) {
    $Output = Join-Path $root $Output
}
$Output = [System.IO.Path]::GetFullPath($Output)
if (-not $Output.StartsWith($tmpPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Release output must be inside release-tmp: $Output"
}

New-Item -ItemType Directory -Path $tmpRoot -Force | Out-Null
New-Item -ItemType Directory -Path $stage | Out-Null

try {
    & $Git -C $root archive --format=zip --output=$source HEAD
    if ($LASTEXITCODE -ne 0) {
        throw 'Git source archive failed.'
    }

    Expand-Archive -LiteralPath $source -DestinationPath $stage
    $dist = Join-Path $stage 'frontend\dist'
    New-Item -ItemType Directory -Path $dist -Force | Out-Null
    Copy-Item -Path (Join-Path $root 'frontend\dist\*') -Destination $dist -Recurse -Force

    $blocked = Get-ChildItem -LiteralPath $stage -Recurse -File | Where-Object {
        $_.Name -like '*.sqlite*' -or
        ($_.Name -like '.env*' -and $_.Name -notlike '*.example') -or
        $_.Name -eq 'app.key'
    }
    if ($blocked) {
        throw "Release contains blocked files: $($blocked.FullName -join ', ')"
    }

    if (Test-Path -LiteralPath $Output) {
        Remove-Item -LiteralPath $Output -Force
    }
    Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $Output -CompressionLevel Optimal
    Get-Item -LiteralPath $Output | Select-Object FullName, Length, LastWriteTime
} finally {
    Remove-Item -LiteralPath $source -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue
}
