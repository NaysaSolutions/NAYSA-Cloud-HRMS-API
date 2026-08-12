param(
    [string]$LibraryPath,
    [string]$JsonFile
)

# ===== Read JSON from file =====
$jsonData = Get-Content -Path $JsonFile -Raw | ConvertFrom-Json

if (-not $jsonData -or $jsonData.Count -eq 0) {
    Write-Error "JSON data is empty."
    exit 1
}

# ===== Load ClosedXML =====
Add-Type -Path (Join-Path $LibraryPath "ClosedXML.dll")
Add-Type -Path (Join-Path $LibraryPath "DocumentFormat.OpenXml.dll")

# ===== Header variables =====
$CompanyName    = "NAYSA Corporation"
$CompanyAddress = "123 Main Street, Manila"
$CompanyTel     = "+63 2 8123 4567"
$ReportTitle    = "Chart of Accounts Report"
$ExtractedBy    = "Arvee Aurelio"
$DateTimeNow    = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")

# ===== Create workbook =====
$wb = New-Object ClosedXML.Excel.XLWorkbook
$ws = $wb.Worksheets.Add("Report")
$ws.Style.Font.FontName = "Calibri"
$ws.Style.Font.FontSize = 11

$rowIndex = 1

# ===== Report Header =====
$ws.Cell($rowIndex,1).Value = $CompanyName
$ws.Cell($rowIndex,1).Style.Font.FontName  = "Calibri"
$ws.Cell($rowIndex,1).Style.Font.FontSize  = 16
$ws.Cell($rowIndex,1).Style.Font.FontColor = [ClosedXML.Excel.XLColor]::Blue
$ws.Cell($rowIndex,1).Style.Font.Bold      = $true
$rowIndex++

$ws.Cell($rowIndex,1).Value = $CompanyAddress; $rowIndex++
$ws.Cell($rowIndex,1).Value = "Tel No: $CompanyTel"; $rowIndex++

$ws.Cell($rowIndex,1).Value = $ReportTitle
$ws.Cell($rowIndex,1).Style.Font.FontSize  = 11
$ws.Cell($rowIndex,1).Style.Font.Bold      = $true
$ws.Cell($rowIndex,1).Style.Font.Italic    = $true
$ws.Cell($rowIndex,1).Style.Font.FontColor = [ClosedXML.Excel.XLColor]::FromHtml("#800080")
$rowIndex++

$ws.Cell($rowIndex,1).Value = "Extracted By: $ExtractedBy"; $ws.Cell($rowIndex,1).Style.Font.FontSize = 9; $rowIndex++
$ws.Cell($rowIndex,1).Value = "Date & Time: $DateTimeNow"; $ws.Cell($rowIndex,1).Style.Font.FontSize = 9; $rowIndex++

$rowIndex++ # Blank row

# ===== Table headers =====
$columns = @($jsonData[0].PSObject.Properties.Name)
for ($i=0; $i -lt $columns.Count; $i++) {
    $cell = $ws.Cell($rowIndex, $i+1)
    $cell.Value = $columns[$i]
    $cell.Style.Font.FontName = "Candara"
    $cell.Style.Font.Bold     = $true
    $cell.Style.Font.FontSize = 10
    $cell.Style.Fill.SetBackgroundColor([ClosedXML.Excel.XLColor]::FromHtml("#ADD8E6"))
    $cell.Style.Alignment.SetHorizontal([ClosedXML.Excel.XLAlignmentHorizontalValues]::Center)
}
$ws.Row($rowIndex).Height = 20
$rowIndex++

# ===== Data rows =====
foreach ($row in $jsonData) {
    for ($i=0; $i -lt $columns.Count; $i++) {
        $colName = $columns[$i]
        $value   = $row.$colName
        $cell    = $ws.Cell($rowIndex, $i+1)

        if ($colName -match '^(AccountNo|Code|RefNo|CustomerID)$') {
            $cell.Style.NumberFormat.Format = "@"
            $cell.Value = "$value"
        }
        elseif ($value -is [DateTime]) {
            $cell.Value = $value
            $cell.Style.DateFormat.Format = "yyyy-MM-dd"
        }
        elseif ($value -is [int] -or $value -is [long]) {
            $cell.Value = $value
            $cell.Style.NumberFormat.Format = "0"
        }
        elseif ($value -is [decimal] -or $value -is [double] -or $value -is [float]) {
            $cell.Value = $value
            $cell.Style.NumberFormat.Format = "#,##0.00"
        }
        else {
            $cell.Style.NumberFormat.Format = "@"
            $cell.Value = "$value"
        }
    }
    $rowIndex++
}

# ===== AutoFilter + BestFit =====
$ws.Range(8,1,$rowIndex-1,$columns.Count).SetAutoFilter()
for ($i=1; $i -le $columns.Count; $i++) { $ws.Column($i).AdjustToContents() }

# ===== Freeze top 7 rows =====
$ws.SheetView.FreezeRows(8)

# ===== Save to MemoryStream and output Base64 =====
$ms = New-Object System.IO.MemoryStream
$wb.SaveAs($ms)
$ms.Seek(0,0) | Out-Null
$bytes  = $ms.ToArray()
$base64 = [Convert]::ToBase64String($bytes)

Write-Output $base64
