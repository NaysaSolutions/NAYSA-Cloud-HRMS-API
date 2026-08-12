param(
    [string]$SprocMode,
    [string]$SprocName,
    [string]$Export,
    [string]$ExportFileName,
    [string]$ReportName, 
    [string]$UserCode, 
    [string]$Branch,
    [datetime]$StartDate,
    [datetime]$EndDate,
    [string]$SCustomer,
    [string]$ECustomer,
    [string]$LibraryPath,
    [string]$ReportPath,
    [string]$DbServer,
    [string]$DbName,
    [string]$DbUser,
    [string]$DbPassword
)


#     # After the export block
if ($Export -eq "Y") {

    Add-Type -Path (Join-Path $LibraryPath "ClosedXML.dll")
    Add-Type -Path (Join-Path $LibraryPath "DocumentFormat.OpenXml.dll")


    
    # ===== Variables for header =====
    $ReportTitle   = "$ReportName (" + $StartDate.ToString("yyyy-MM-dd") + " to " + $EndDate.ToString("yyyy-MM-dd") + ")"
    $DateTimeNow   = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")  
    $ExtractedBy   = "Extracted By : NAYSA Admin"



    # ===== SQL Connection =====
    $SqlConnectionString = "Server=$DbServer;Database=$DbName;User Id=$DbUser;Password=$DbPassword;"
    $conn = New-Object System.Data.SqlClient.SqlConnection $SqlConnectionString
    $cmd = $conn.CreateCommand()
    $cmd.CommandType = [System.Data.CommandType]::StoredProcedure
    $cmd.CommandText = $SprocName

    $parameters = @{
        "@mode"       = $SprocMode
        "@branchcode" = $Branch
        "@StartDate"  = $StartDate
        "@EndDate"    = $EndDate
        "@sCustomer"  = $SCustomer
        "@eCustomer"  = $ECustomer
    }
    foreach ($key in $parameters.Keys) {
        $null = $cmd.Parameters.AddWithValue($key, $parameters[$key])
    }

    $conn.Open()
    $reader = $cmd.ExecuteReader()
    $table = New-Object System.Data.DataTable
    $table.Load($reader)
    



    # =============================
    # 2. Fetch Company Information
    # =============================
    $CompanyQuery = "select top 1 comp_name compName,concat(comp_addr1,comp_addr2,comp_addr3) address, tel_no telNo from company"
    $cmd2 = $conn.CreateCommand()
    $cmd2.CommandType = [System.Data.CommandType]::Text
    $cmd2.CommandText = $CompanyQuery
    $reader2 = $cmd2.ExecuteReader()
    $companyTable = New-Object System.Data.DataTable
    $companyTable.Load($reader2)
    $reader2.Close()
    if ($companyTable.Rows.Count -gt 0) {
        $CompanyName    = $companyTable.Rows[0].compName
        $CompanyAddress = "Address : " +  $companyTable.Rows[0].address
        $CompanyTel     = "Tel No : " + $companyTable.Rows[0].telNo
    }


    
    $CompanyQuery = "select top 1 user_name userName from users where user_code ='$UserCode'"
    $cmd3 = $conn.CreateCommand()
    $cmd3.CommandType = [System.Data.CommandType]::Text
    $cmd3.CommandText = $CompanyQuery
    $reader3 = $cmd3.ExecuteReader()
    $userTable = New-Object System.Data.DataTable
    $userTable.Load($reader3)
    $reader3.Close()
    $conn.Close()

    if ($userTable.Rows.Count -gt 0) {
        $ExtractedBy   = "Extracted By : " + $userTable.Rows[0].userName
    }

   


    # ===== Create Excel workbook =====
    $wb = New-Object ClosedXML.Excel.XLWorkbook
    $ws = $wb.Worksheets.Add("Report")

    # ===== Set default font Calibri 11 =====
    $ws.Style.Font.FontName = "Calibri"
    $ws.Style.Font.FontSize = 11

    $rowIndex = 1

    # ===== Report Header Section =====

    # Company Name (Calibri 16, Blue, Bold)
    $ws.Cell($rowIndex,1).Value = $CompanyName
    $ws.Cell($rowIndex,1).Style.Font.FontName = "Calibri"
    $ws.Cell($rowIndex,1).Style.Font.FontSize = 16
    $ws.Cell($rowIndex,1).Style.Font.FontColor = [ClosedXML.Excel.XLColor]::Blue
    $ws.Cell($rowIndex,1).Style.Font.Bold = $true
    $rowIndex++

    # Address, Tel, Report Title, Extracted By, Date & Time
    $ws.Cell($rowIndex,1).Value = $CompanyAddress; $rowIndex++
    $ws.Cell($rowIndex,1).Value = "Tel No: $CompanyTel"; $rowIndex++

    # Report Title (Calibri 14, Bold)
    $ws.Cell($rowIndex,1).Value = $ReportTitle
    $ws.Cell($rowIndex,1).Style.Font.FontName = "Calibri"
    $ws.Cell($rowIndex,1).Style.Font.FontSize = 11
    $ws.Cell($rowIndex,1).Style.Font.Bold = $true
    $ws.Cell($rowIndex,1).Style.Font.Italic = $true
    $ws.Cell($rowIndex,1).Style.Font.FontColor = [ClosedXML.Excel.XLColor]::FromHtml("#800080")
    $rowIndex++

    $ws.Cell($rowIndex,1).Value = "Extracted By: $ExtractedBy"
    $ws.Cell($rowIndex,1).Style.Font.FontSize = 9
    $rowIndex++
    $ws.Cell($rowIndex,1).Value = "Date & Time: $DateTimeNow"
    $ws.Cell($rowIndex,1).Style.Font.FontSize = 9
    $rowIndex++

    # Blank row
    $rowIndex++

    # ===== Table Headers =====
    for ($i=0; $i -lt $table.Columns.Count; $i++) {
        $cell = $ws.Cell($rowIndex, $i+1)
        $cell.Value = $table.Columns[$i].ColumnName

        # Font styling
        $cell.Style.Font.FontName = "Candara"
        $cell.Style.Font.Bold = $true
        $cell.Style.Font.FontSize = 10

        # Background color
        $cell.Style.Fill.SetBackgroundColor([ClosedXML.Excel.XLColor]::FromHtml("#ADD8E6"))

        # Alignment
        $cell.Style.Alignment.SetHorizontal([ClosedXML.Excel.XLAlignmentHorizontalValues]::Center)
    }

    # Set row height
    $ws.Row($rowIndex).Height = 20

    $rowIndex++
    # ===== Data Rows =====
    foreach ($row in $table.Rows) {
        for ($i=0; $i -lt $table.Columns.Count; $i++) {
            $value   = $row[$i]
            $cell    = $ws.Cell($rowIndex, $i+1)
            $colName = $table.Columns[$i].ColumnName

            if ($colName -match '^(AccountNo|Code|RefNo|CustomerID)$') {
                # Preserve leading zeros as text
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
                # Default text
                $cell.Style.NumberFormat.Format = "@"
                $cell.Value = "$value"
            }
        }
        $rowIndex++
    }

    # ===== AutoFilter + BestFit =====
    $ws.Range(8,1,$rowIndex-1,$table.Columns.Count).SetAutoFilter()
    for ($i=1; $i -le $table.Columns.Count; $i++) {
        $ws.Column($i).AdjustToContents()
    }

    # ===== Freeze Top 7 Rows =====
    $ws.SheetView.FreezeRows(8)

    # ===== Save Excel =====
    $wb.SaveAs($ExportFileName)

    Write-Output "Excel file exported to $ExportFileName"

}






else {
try {
    Add-Type -Path (Join-Path $LibraryPath "CrystalDecisions.CrystalReports.Engine.dll")
    Add-Type -Path (Join-Path $LibraryPath "CrystalDecisions.Shared.dll")
}
catch {
    Write-Error "Failed to load Crystal Reports DLLs: $_"
    exit 1
}

try {
    $report = New-Object CrystalDecisions.CrystalReports.Engine.ReportDocument
    $ReportPath = "$ReportPath.rpt"
    $report.Load($ReportPath)
}
catch {
    Write-Error "Failed to load report '$ReportPath': $_"
    exit 1
}

try {
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
}
catch {
    Write-Error "Failed to connect to database or apply login info: $_"
    exit 1
}

try {
    # === SET REPORT PARAMETERS ===
    $report.SetParameterValue(0, $Branch) 
    $report.SetParameterValue(1, $StartDate) 
    $report.SetParameterValue(2, $EndDate) 
    $report.SetParameterValue(3, $SCustomer) 
    $report.SetParameterValue(4, $ECustomer)
}
catch {
    Write-Error "Failed to set report parameters: $_"
    exit 1
}

try {
    $stream = $report.ExportToStream([CrystalDecisions.Shared.ExportFormatType]::PortableDocFormat)
    $memoryStream = New-Object System.IO.MemoryStream
    $stream.CopyTo($memoryStream)
    $fileBytes = $memoryStream.ToArray()
    [Console]::Write([Convert]::ToBase64String($fileBytes))
}
catch {
    Write-Error "Failed to export report: $_"
    exit 1
}

}