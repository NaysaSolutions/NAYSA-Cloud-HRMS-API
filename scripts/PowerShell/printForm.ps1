
param(
    [string]$TranID,
    [string]$LibraryPath,
    [string]$ReportPath,
    [string]$DbServer,
    [string]$DbName,
    [string]$DbUser,
    [string]$DbPassword
)


# === LOAD CRYSTAL REPORTS DLLs ===
Add-Type -Path (Join-Path $LibraryPath "CrystalDecisions.CrystalReports.Engine.dll")
Add-Type -Path (Join-Path $LibraryPath "CrystalDecisions.Shared.dll")


# === LOAD REPORT ===
$report = New-Object CrystalDecisions.CrystalReports.Engine.ReportDocument
$report.Load($ReportPath)

# === DB LOGIN INFO FOR CRYSTAL ===
$connectionInfo = New-Object CrystalDecisions.Shared.ConnectionInfo
$connectionInfo.ServerName   = $DbServer
$connectionInfo.DatabaseName = $DbName
$connectionInfo.UserID       = $DbUser
$connectionInfo.Password     = $DbPassword

# Apply login info to all tables in main report
foreach ($table in $report.Database.Tables) {
    $tableLogonInfo = $table.LogOnInfo
    $tableLogonInfo.ConnectionInfo = $connectionInfo
    $table.ApplyLogOnInfo($tableLogonInfo)
}

# Apply login info to all subreports
foreach ($section in $report.ReportDefinition.Sections) {
    foreach ($reportObject in $section.ReportObjects) {
        if ($reportObject.Kind -eq 'SubreportObject') {
            $subreport = $report.OpenSubreport($reportObject.SubreportName)
            foreach ($table in $subreport.Database.Tables) {
                $tableLogonInfo = $table.LogOnInfo
                $tableLogonInfo.ConnectionInfo = $connectionInfo
                $table.ApplyLogOnInfo($tableLogonInfo)
            }
        }
    }
}

# === SET REPORT PARAMETERS ===
$report.SetParameterValue(0, $TranID) # Example: Document Number


# === EXPORT REPORT DIRECTLY TO STREAM ===
$stream = $report.ExportToStream([CrystalDecisions.Shared.ExportFormatType]::PortableDocFormat)

# Convert MemoryStream to byte array
$memoryStream = New-Object System.IO.MemoryStream
$stream.CopyTo($memoryStream)
$fileBytes = $memoryStream.ToArray()

# === RETURN AS BASE64 (SAFE FOR LARAVEL CAPTURE) ===
# Avoid BOM/extra characters — write clean Base64 only
[Console]::Write([Convert]::ToBase64String($fileBytes))
