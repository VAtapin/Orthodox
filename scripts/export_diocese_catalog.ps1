param([string[]]$DocumentNames = @('Prihod_proposal', 'Prihod_brief', 'Diocese_sites_appendix'))
$ErrorActionPreference = 'Stop'
$catalogRoot = Join-Path $PSScriptRoot '../docs/diocese-catalog'
$catalogWord = New-Object -ComObject Word.Application
$catalogWord.Visible = $false
$catalogWord.DisplayAlerts = 0
try {
    foreach ($catalogName in $DocumentNames) {
        $catalogDocx = [IO.Path]::GetFullPath((Join-Path $catalogRoot "$catalogName.docx"))
        $catalogPdf = [IO.Path]::GetFullPath((Join-Path $catalogRoot "$catalogName.pdf"))
        $catalogDocument = $catalogWord.Documents.Open($catalogDocx, $false, $true)
        try {
            $catalogDocument.Repaginate()
            $catalogDocument.ExportAsFixedFormat($catalogPdf, 17)
            Write-Output "$catalogName pages: $($catalogDocument.ComputeStatistics(2))"
        } finally { $catalogDocument.Close(0) }
    }
} finally { $catalogWord.Quit() }
