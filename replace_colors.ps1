
$targetDirs = @("frontend/src")
$targetFiles = @("frontend/index.html")

$mappings = @(
    @{ K = "purple"; V = "teal" },
    @{ K = "violet"; V = "cyan" },
    @{ K = "indigo"; V = "emerald" },
    @{ K = "Purple"; V = "Teal" },
    @{ K = "Violet"; V = "Cyan" },
    @{ K = "Indigo"; V = "Emerald" },
    @{ K = "PURPLE"; V = "TEAL" },
    @{ K = "VIOLET"; V = "CYAN" },
    @{ K = "INDIGO"; V = "EMERALD" },
    @{ K = "#8b5cf6"; V = "#0d9488" },
    @{ K = "#8B5CF6"; V = "#0D9488" },
    @{ K = "#a855f7"; V = "#0d9488" },
    @{ K = "#A855F7"; V = "#0D9488" },
    @{ K = "#6366f1"; V = "#059669" },
    @{ K = "#6366F1"; V = "#059669" },
    @{ K = "#c084fc"; V = "#2dd4bf" },
    @{ K = "#C084FC"; V = "#2DD4BF" }
)

function Process-File($filePath) {
    try {
        $content = Get-Content -Path $filePath -Raw -ErrorAction Stop
        if ([string]::IsNullOrEmpty($content)) { return }
        $changed = $false

        foreach ($m in $mappings) {
            if ($content.Contains($m.K)) {
                $content = $content.Replace($m.K, $m.V)
                $changed = $true
            }
        }

        if ($changed) {
            Set-Content -Path $filePath -Value $content -NoNewline -Encoding UTF8
            Write-Host "Updated: $filePath"
        }
    } catch {
        Write-Error "Error processing $($filePath): $($_.Exception.Message)"
    }
}

foreach ($dir in $targetDirs) {
    if (Test-Path $dir) {
        $files = Get-ChildItem -Path $dir -Recurse -Include *.tsx, *.ts, *.css, *.html
        foreach ($file in $files) {
            Process-File $file.FullName
        }
    }
}

foreach ($file in $targetFiles) {
    if (Test-Path $file) {
        Process-File (Resolve-Path $file).Path
    }
}
