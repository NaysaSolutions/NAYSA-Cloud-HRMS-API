USE [Anywhere]
GO

/****** Object:  View [dbo].[Vw_vbnet_form_docno]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[Vw_vbnet_form_docno]
GO

/****** Object:  View [dbo].[Vw_vbnet_form_cvdtlb]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[Vw_vbnet_form_cvdtlb]
GO

/****** Object:  View [dbo].[Vw_vbnet_form_companyID]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[Vw_vbnet_form_companyID]
GO

/****** Object:  View [dbo].[Vw_vbnet_form_version]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[Vw_vbnet_form_version]
GO

/****** Object:  View [dbo].[Vw_vbnet_Form_CVHDR]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[Vw_vbnet_Form_CVHDR]
GO

/****** Object:  View [dbo].[vw_vbnet_form_docstamp]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[vw_vbnet_form_docstamp]
GO

/****** Object:  View [dbo].[Vw_vbnet_form_cvdtla]    Script Date: 09/22/2025 9:18:15 am ******/
DROP VIEW [dbo].[Vw_vbnet_form_cvdtla]
GO

/****** Object:  View [dbo].[Vw_vbnet_form_cvdtla]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO



CREATE view [dbo].[Vw_vbnet_form_cvdtla]

as

select 'PWAPV' as type,isnull(c.with_apv,'N') as with_apv,a.branch_code as branchcode,a.cv_no,a.cv_id,a.cv_date,c.curr_code,c.curr_rate,a.apv_no,a.si_no,a.si_date,a.balance,a.vat_amount as vat_amt,a.atc_amount as ewt_amt,isnull(b.advpo_atc_amount,0) as adv_ewt_app ,a.applied_amount as applied from cv_dt1 as a
join apv_dt1 as b on a.apdt1_lineno = b.line_no and a.apv_no = b.apv_no and a.branch_code = b.branch_code
left join apv_hd as f on f.apv_no = a.apv_no and f.branch_code = a.branch_code
join cv_hd as c on a.cv_no = c.cv_no and a.branch_code = c.branch_code
where isnull(c.with_apv,'N') = 'Y' and isnull(f.apvtran_type,'') <> 'APV02' and c.curr_code = 'PHP'

union all
select 'PWAPV' as type,isnull(c.with_apv,'N') as with_apv,a.branch_code as branchcode,a.cv_no,a.cv_id,a.cv_date,c.curr_code,c.curr_rate,a.apv_no,a.si_no,a.si_date,a.balance,a.vat_amount as vat_amt,a.atc_amount as ewt_amt,0 as adv_ewt_app ,a.applied_amount as applied from cv_dt1 as a
join apv_dt2 as b on  a.apv_no = b.apv_no and a.branch_code = b.branch_code and b.acct_code = a.ap_acct
join apv_hd as f on f.apv_no = a.apv_no and f.branch_code = a.branch_code
join cv_hd as c on a.cv_no = c.cv_no and a.branch_code = c.branch_code
where f.apvtran_type = 'APV02' and c.curr_code = 'PHP'



union all

select 'FWAPV' as type,isnull(c.with_apv,'N') as with_apv,a.branch_code as branchcode,a.cv_no,a.cv_id,a.cv_date,c.curr_code,c.curr_rate,a.apv_no,a.si_no,a.si_date,a.balance,a.vat_amount as vat_amt,a.atc_amount as ewt_amt,isnull(b.advpo_atc_amount,0) as adv_ewt_app ,a.applied_amount as applied from cv_dt1 as a
join apv_dt1 as b on a.apdt1_lineno = b.line_no and a.apv_no = b.apv_no and a.branch_code = b.branch_code
left join apv_hd as f on f.apv_no = a.apv_no and f.branch_code = a.branch_code
join cv_hd as c on a.cv_no = c.cv_no and a.branch_code = c.branch_code
where isnull(c.with_apv,'N') = 'Y' and isnull(f.apvtran_type,'') <> 'APV02' and c.curr_code <> 'PHP'

union all
select 'FWAPV' as type,isnull(c.with_apv,'N') as with_apv,a.branch_code as branchcode,a.cv_no,a.cv_id,a.cv_date,c.curr_code,c.curr_rate,a.apv_no,a.si_no,a.si_date,a.balance,a.vat_amount as vat_amt,a.atc_amount as ewt_amt,0 as adv_ewt_app ,a.applied_amount as applied from cv_dt1 as a
join apv_dt2 as b on  a.apv_no = b.apv_no and a.branch_code = b.branch_code and b.acct_code = a.ap_acct
join apv_hd as f on f.apv_no = a.apv_no and f.branch_code = a.branch_code
join cv_hd as c on a.cv_no = c.cv_no and a.branch_code = c.branch_code
where f.apvtran_type = 'APV02' and c.curr_code <> 'PHP'

union all

select 'PWOAPV' as type,isnull(c.with_apv,'N') as with_apv,a.branch_code as branchcode,a.cv_no,a.cv_id,a.cv_date,c.curr_code,c.curr_rate,a.apv_no,a.si_no,a.si_date,a.balance,a.vat_amount as vat_amt,a.atc_amount as ewt_amt,isnull(b.advpo_atc_amount,0) as adv_ewt_app ,a.amount_due as applied from cv_dt1 as a
left join apv_dt1 as b on a.apdt1_lineno = b.line_no and a.apv_no = b.apv_no and a.branch_code = b.branch_code
join cv_hd as c on a.cv_no = c.cv_no and a.branch_code = c.branch_code
where isnull(c.with_apv,'N') <> 'Y' and c.curr_code = 'PHP'

union all

select 'FWOAPV' as type,isnull(c.with_apv,'N') as with_apv,a.branch_code as branchcode,a.cv_no,a.cv_id,a.cv_date,c.curr_code,c.curr_rate,a.apv_no,a.si_no,a.si_date,a.balance,a.vat_amount as vat_amt,a.atc_amount as ewt_amt,isnull(b.advpo_atc_amount,0) as adv_ewt_app ,a.amount_due as applied from cv_dt1 as a
left join apv_dt1 as b on a.apdt1_lineno = b.line_no and a.apv_no = b.apv_no and a.branch_code = b.branch_code
join cv_hd as c on a.cv_no = c.cv_no and a.branch_code = c.branch_code
where isnull(c.with_apv,'N') <> 'Y' and c.curr_code <> 'PHP'


GO

/****** Object:  View [dbo].[vw_vbnet_form_docstamp]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO



CREATE VIEW [dbo].[vw_vbnet_form_docstamp]

as

select a.branch_code as branchcode,a.doc_id as doc_type,a.tran_id as doc_id,a.prepared_by,isnull(a.checked_by,'') as checked_by,isnull(a.noted_by,'') as noted_by,
isnull(a.approved_by,'') as approved_by,'' as with_noted from doc_sign as a
--left join ars_doc as b on a.doc_type = b.doc_type


GO

/****** Object:  View [dbo].[Vw_vbnet_Form_CVHDR]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO



CREATE VIEW [dbo].[Vw_vbnet_Form_CVHDR]
AS
 

SELECT 
       CASE WHEN isnull(A.with_apv,'N') = 'Y' and A.curr_code = 'PHP' THEN 'PWAPV'
	   WHEN isnull(A.with_apv,'N') = 'Y' and A.curr_code <> 'PHP' THEN 'FWAPV'    
	   WHEN isnull(A.with_apv,'N') <> 'Y' and A.curr_code = 'PHP' AND A.CVTRAN_TYPE IN ('APV001','CV_APTYPE') THEN 'PWOAPVP'
	   WHEN isnull(A.with_apv,'N') <> 'Y' and A.curr_code <> 'PHP' AND A.CVTRAN_TYPE IN ('APV001','CV_APTYPE') THEN 'FWOAPVP'  
	   WHEN isnull(A.with_apv,'N') <> 'Y' and A.curr_code = 'PHP' THEN 'PWOAPV'
	   WHEN isnull(A.with_apv,'N') <> 'Y' and A.curr_code <> 'PHP' THEN 'FWOAPV'
	   END as CV_TYPE,
	   'CV' AS DOC_TYPE,
	   I.DOC_NAME AS DOC_DESCFORM,
	   A.BRANCH_CODE AS BRANCHCODE,
       E.BRANCH_NAME AS BRANCHNAME,
       A.CV_NO,
	   A.CV_ID,
       A.CV_DATE,
       A.VEND_CODE,
       A.VEND_NAME,
       B.VEND_ADDR1 AS ADDRESS1,
       B.VEND_ADDR2 AS ADDRESS2,
       B.VEND_ADDR3 AS ADDRESS3,
       B.VEND_TIN AS TIN,
       A.CURR_CODE,
       C.CURR_NAME,
       A.CURR_RATE,
       SUBSTRING(A.REMARKS,1,8000) AS PARTICULAR,
       A.CVTRAN_TYPE AS AP_TYPE,
       D.DROPDOWN_NAME AS 'TYPE',
       H.BANKTYPE_NAME AS DESCRIP,
       A.CHECK_NO,
       A.CHECK_DATE,
       A.CURR_AMOUNT AS ORIG_AMT,
       'Y' AS CAS, 
       B.BUSINESS_NAME,
       A.REFCV_NO,
	   ISNULL(A.WITH_APV,'N') AS WITH_APV ,
	   '' AS with_busstyle,
	   '' AS ISO_NO
FROM CV_HD AS A
       INNER JOIN VEND_MAST AS B ON A.VEND_CODE = B.VEND_CODE
       INNER JOIN CURR_REF AS C ON A.CURR_CODE = C.CURR_CODE
       INNER JOIN HS_DROPDOWN AS D ON A.CV_TYPE = D.DROPDOWN_CODE AND D.DOC_CODE = 'CV'
       INNER JOIN BRANCH_REF AS E ON A.BRANCH_CODE = E.BRANCH_CODE
       INNER JOIN BANK_MAST AS G ON A.BANK_CODE = G.BANK_CODE
       INNER JOIN BANK_REF AS H ON G.BANKTYPE_CODE = H.BANKTYPE_CODE
	   INNER JOIN HS_DOC AS I ON I.DOC_ID = 'CV'
       --CROSS JOIN NAYSA_BIR
 
 
GO

/****** Object:  View [dbo].[Vw_vbnet_form_version]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


CREATE    VIEW [dbo].[Vw_vbnet_form_version]

AS

select '' as release_no
		,'NAYSA FINANCIALS CLOUD V12' as version
		,'NAYSA-SOLUTIONS INC' as licensed_by
		,'N' as cas 
from naysa_parm as a
--cross join naysa_bir as b


GO

/****** Object:  View [dbo].[Vw_vbnet_form_companyID]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


CREATE    view [dbo].[Vw_vbnet_form_companyID]
as

select a.comp_name as company_name,b.branch_code branchcode,'VAT REG' classification,
b.branch_addr1 as address1,b.branch_addr2 as address2,b.branch_addr3 as address3,
b.tel_no as tel_no,b.fax_no as fax_no,'naysa.solutioninc@gmail.com' as email,
b.branch_tin as tin,'V11' as version 
from naysa_parm as a cross join branch_ref as b

GO

/****** Object:  View [dbo].[Vw_vbnet_form_cvdtlb]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


CREATE   VIEW [dbo].[Vw_vbnet_form_cvdtlb]
AS

with cteCVDetail as (select a.branch_code as branchcode,b.vend_code,a.cv_no,a.cv_date,b.cv_id,
						a.rec_no,a.acct_code,a.rc_code as act_code,a.sl_code,a.particular,
						a.vat_code,c.vat_name as vat_desc,a.atc_code as ewt_code,d.atc_name as ewt_desc,
						debit,credit,debit_fx1,credit_fx1,debit_fx2,credit_fx2,
						case when debit >0 then 'dr' else 'cr' end as entry_val 
						from cv_dt2 as a
						join cv_hd as b on b.cv_id = a.cv_id
						left join vat_ref as c on c.vat_code = a.vat_code
						left join atc_ref as d on d.atc_code = a.atc_code)




SELECT A.BRANCHCODE,
        A.VEND_CODE,
        A.CV_NO,
		A.CV_ID,
        A.REC_NO,
        A.ACCT_CODE,
        A.ACT_CODE,
        A.SL_CODE,
        A.PARTICULAR,
        A.VAT_CODE,
        A.VAT_DESC,
        A.EWT_CODE,
        A.EWT_DESC,
        A.ACCT_BAL,
        CASE WHEN A.DEBIT > 0 AND A.CREDIT > 0 AND A.DEBIT > A.CREDIT AND A.ACCT_BAL ='DR' THEN A.DEBIT - A.CREDIT
                WHEN A.DEBIT > 0 AND A.CREDIT > 0 AND A.DEBIT > A.CREDIT AND A.ACCT_BAL ='CR' THEN 0
                WHEN A.DEBIT > 0 AND A.CREDIT > 0 AND A.DEBIT < A.CREDIT AND A.ACCT_BAL ='CR' THEN 0        
        ELSE A.DEBIT END AS DEBIT,

        CASE WHEN A.DEBIT > 0 AND A.CREDIT > 0 AND A.DEBIT < A.CREDIT AND A.ACCT_BAL ='CR' THEN A.CREDIT - A.DEBIT
                WHEN A.DEBIT > 0 AND A.CREDIT > 0 AND A.DEBIT > A.CREDIT AND A.ACCT_BAL ='DR' THEN 0
                WHEN A.DEBIT > 0 AND A.CREDIT > 0 AND A.DEBIT < A.CREDIT AND A.ACCT_BAL ='DR' THEN 0
        ELSE A.CREDIT END AS CREDIT,
        A.DEBIT_FX1,
        A.CREDIT_FX1,
        A.DEBIT_FX2,
        A.CREDIT_FX2,
        A.CURR_CODE2,
        A.CURR_CODE3
    FROM (

    SELECT B.ACCT_BALANCE AS ACCT_BAL,A.BRANCHCODE,A.VEND_CODE ,A.CV_NO, A.CV_ID ,MIN(A.REC_NO) AS REC_NO, A.ACCT_CODE,A.ACT_CODE,A.SL_CODE,A.PARTICULAR,A.VAT_CODE,A.VAT_DESC,A.EWT_CODE,A. EWT_DESC,    
            SUM(DEBIT) AS DEBIT,SUM(CREDIT) AS CREDIT,SUM(DEBIT_FX1) AS DEBIT_FX1 , SUM(CREDIT_FX1) AS CREDIT_FX1,SUM(DEBIT_FX2) AS DEBIT_FX2 , SUM(CREDIT_FX2) AS CREDIT_FX2,
            (SELECT GL_CURRGLOBAL2 FROM HS_OPTION) AS CURR_CODE2,(SELECT GL_CURRGLOBAL3 FROM HS_OPTION) AS CURR_CODE3
    FROM cteCVDetail AS A
        JOIN COA_MAST AS B ON B.ACCT_CODE = A.ACCT_CODE
        --LEFT JOIN VEND_MAST AS C ON A.EWT_CODE = C.EWT_CODE
    GROUP BY B.ACCT_BALANCE,A.BRANCHCODE,A.VEND_CODE, A.CV_NO, A.CV_ID, A.ACCT_CODE,A.ACT_CODE,A.SL_CODE,A.PARTICULAR,A.VAT_CODE,A.VAT_DESC,A.EWT_CODE,A.EWT_DESC,entry_val

        ) A



GO

/****** Object:  View [dbo].[Vw_vbnet_form_docno]    Script Date: 09/22/2025 9:18:15 am ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO



create    VIEW [dbo].[Vw_vbnet_form_docno]

AS



----PR TRANSACTION--

--select a.branchcode,a.pr_no as doc_no,'PR' as doc_type,z.doc_desc,case when isnull(to_doctype,'') = '' then z.doc_type else z.to_doctype end as to_doctype,
--no_reprints = DBO.fnNoReprint(no_reprints,'PR',branchcode,pr_no),'' as 'Reprint label'
--from prhdr as a
--cross join ars_doc as z where z.doc_type = 'PR' and isnull(form_print1,'') <> '' 


--union all

----PO TRANSACTION--

--select a.branchcode,a.po_no as doc_no,'PO' as doc_type,z.doc_desc,case when isnull(to_doctype,'') = '' then z.doc_type else z.to_doctype end as to_doctype,
--no_reprints = DBO.fnNoReprint(no_reprints,'PO',branchcode,po_no),'' as 'Reprint label'
--from pohdr as a
--cross join ars_doc as z where z.doc_type = 'PO' and isnull(form_print1,'') <> '' 

--union all

----JO TRANSACTION--

--select a.branchcode,a.jo_no as doc_no,'JO' as doc_type,z.doc_desc,case when isnull(to_doctype,'') = '' then z.doc_type else z.to_doctype end as to_doctype,
--no_reprints = DBO.fnNoReprint(no_reprints,'JO',branchcode,jo_no),'' as 'Reprint label'
--from johdr as a
--cross join ars_doc as z where z.doc_type = 'JO' and isnull(form_print1,'') <> '' 

--UNION ALL

----SVO TRANSACTION--

--select BRANCHCODE,SVO_NO AS DOC_NO,'SVO' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'SVO',branchcode,svo_no),'' as 'Reprint label'
--from svohdr as a
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'SVO' and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----MSRR TRANSACTION--

--select BRANCHCODE,RR_NO AS DOC_NO,'MSRR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'MSRR',branchcode,rr_no),'' as 'Reprint label'
--FROM GSRRHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'MSRR'and isnull(FORM_PRINT1,'') <> ''



--UNION ALL

----MSIS TRANSACTION--

--select BRANCHCODE,RIS_NO AS DOC_NO,'MSIS' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'MSIS',branchcode,ris_no),'' as 'Reprint label'
--FROM GSRISHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'MSIS' and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----MSST TRANSACTION--

--select BRANCHCODE,WT_NO AS DOC_NO,'MSST' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'MSST',branchcode,wt_no),'' as 'Reprint label'
--FROM GSWTHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'MSST'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----MSADJ TRANSACTION--

--select BRANCHCODE,ADJ_NO AS DOC_NO,'MSADJ' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'MSADJ',branchcode,adj_no) ,'' as 'Reprint label'
--FROM GSADJHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'MSADJ'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----MSRTV TRANSACTION--

--select BRANCHCODE,RTV_NO AS DOC_NO,'MSRTV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'MSRTV',branchcode,rtv_no) ,'' as 'Reprint label'
--FROM GSRTVHDR
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'MSRTV'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FGSR TRANSACTION--

--select BRANCHCODE,SR_NO AS DOC_NO,'FGSR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FGSR',branchcode,sr_no) ,'' as 'Reprint label'
--FROM FGSRHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'FGSR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FGRR TRANSACTION--

--select BRANCHCODE,RR_NO AS DOC_NO,'FGRR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FGRR',branchcode,rr_no) ,'' as 'Reprint label'
--FROM FGRRHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'FGRR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FGIS TRANSACTION--

--select BRANCHCODE,RIS_NO AS DOC_NO,'FGIS' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FGIS',branchcode,ris_no) ,'' as 'Reprint label'
--FROM FGRISHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'FGIS'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FGST TRANSACTION--

--select BRANCHCODE,WT_NO AS DOC_NO,'FGST' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FGST',branchcode,wt_no) ,'' as 'Reprint label'
--FROM FGWTHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'FGST'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FGADJ TRANSACTION--

--select BRANCHCODE,ADJ_NO AS DOC_NO,'FGADJ' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FGADJ',branchcode,adj_no) ,'' as 'Reprint label'
--FROM FADJHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='FGADJ'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FGRTV TRANSACTION--

--select BRANCHCODE,RTV_NO AS DOC_NO,'FGRTV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FGRTV',branchcode,rtv_no) ,'' as 'Reprint label'
--FROM FGRTVHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='FGRTV'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----RMRR TRANSACTION--

--select BRANCHCODE,RR_NO AS DOC_NO,'RMRR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'RMRR',branchcode,rr_no) ,'' as 'Reprint label'
--FROM RMRRHDR
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='RMRR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----RMST TRANSACTION--

--select BRANCHCODE,WT_NO AS DOC_NO,'RMST' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'RMST',branchcode,wt_no) ,'' as 'Reprint label'
--FROM RMWTHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='RMST'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----RMIS TRANSACTION--

--select BRANCHCODE,RIS_NO AS DOC_NO,'RMIS' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'RMIS',branchcode,ris_no) ,'' as 'Reprint label'
--FROM RMRISHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='RMIS'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----RMADJ TRANSACTION--

--select BRANCHCODE,ADJ_NO AS DOC_NO,'RMADJ' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'RMADJ',branchcode,adj_no) ,'' as 'Reprint label'
--FROM RMADJHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='RMADJ'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----RMRTV TRANSACTION--

--select BRANCHCODE,RTV_NO AS DOC_NO,'RMRTV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'RMRTV',branchcode,rtv_no) ,'' as 'Reprint label'
--FROM RMRTVHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='RMRTV'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL


----RMRFP TRANSACTION--

--select a.branchcode,a.rfp_no as doc_no,'RMRFP' as doc_type,z.doc_desc,case when isnull(to_doctype,'') = '' then z.doc_type else z.to_doctype end as to_doctype,
--no_reprints = DBO.fnNoReprint(no_reprints,'RMRFP',branchcode,rfp_no),'' as 'Reprint label'
--from rmrfphdr as a
--cross join ars_doc as z where z.doc_type = 'RMRFP' and isnull(form_print1,'') <> '' 

--UNION ALL

----APV TRANSACTION--

--select BRANCHCODE,APV_NO AS DOC_NO,'APV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'APV',branchcode,apv_no)  ,'' as 'Reprint label'
--FROM APVHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='APV'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

--CV TRANSACTION--

select BRANCH_CODE AS BRANCHCODE,CV_NO AS DOC_NO,CV_ID AS DOC_ID,'CV' AS DOC_TYPE,B.DOC_NAME AS DOC_DESC,'' AS TO_DOCTYPE
--,no_reprints = DBO.fnNoReprint(no_reprints,'CV',branchcode,cv_no)
,'ORIGINAL COPY' AS no_reprints
,'' as 'Reprint label'
FROM CV_HD AS A
CROSS JOIN HS_DOC AS B WHERE DOC_ID = 'CV'


--UNION ALL

----APDM TRANSACTION--

--select BRANCHCODE,DOC_NO AS DOC_NO,'APDM' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'APDM',branchcode,doc_no) ,'' as 'Reprint label'
--FROM APDMHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE = 'APDM'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----APCM TRANSACTION--

--select BRANCHCODE,DOC_NO AS DOC_NO,'APCM' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'APCM',branchcode,doc_no) ,'' as 'Reprint label'
--FROM APCMHDR  
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='APCM'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----SO TRANSACTION--

--select BRANCHCODE,SO_NO AS DOC_NO,'SO' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'SO',branchcode,so_no) ,'' as 'Reprint label'
--FROM SOHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='SO'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----DR TRANSACTION--

--select BRANCHCODE,DR_NO AS DOC_NO,'DR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'DR',branchcode,dr_no)  ,'' as 'Reprint label'
--FROM DRHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='DR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----DRC TRANSACTION--

--select BRANCHCODE,DR_NO AS DOC_NO,'DRC' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'DRC',branchcode,dr_no) ,'' as 'Reprint label'
--FROM DRCHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='DRC'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----SI TRANSACTION--

--select BRANCHCODE,SI_NO AS DOC_NO,'SI' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'SI',branchcode,si_no) ,'' as 'Reprint label'
--FROM SIHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='SI'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----CI TRANSACTION--

--select BRANCHCODE,CI_NO AS DOC_NO,'CI' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'CI',branchcode,ci_no) ,'' as 'Reprint label'
--FROM CIHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='CI'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----CSI TRANSACTION--

--select BRANCHCODE,CSI_NO AS DOC_NO,'CSI' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'CSI',branchcode,csi_no) ,'' as 'Reprint label'
--FROM CSIHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='CSI'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----BI TRANSACTION--

--select BRANCHCODE,SOA_NO AS DOC_NO,'BI' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'BI',branchcode,soa_no) ,'' as 'Reprint label'
--FROM SOAHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='BI'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----SOA TRANSACTION--

--select BRANCHCODE,SOA_NO AS DOC_NO,'SOA' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'SOA',branchcode,soa_no) ,'' as 'Reprint label'
--FROM SOAHDR1  
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='SOA'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----BS TRANSACTION--

--select BRANCHCODE,SOA_NO AS DOC_NO,'BS' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'BS',branchcode,soa_no) ,'' as 'Reprint label'
--FROM SOAHDR2 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='BS'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----SVI TRANSACTION--

--select BRANCHCODE,SVI_NO AS DOC_NO,'SVI' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'SVI',branchcode,svi_no) ,'' as 'Reprint label'
--FROM SVIHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='SVI'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----OR TRANSACTION--

--select BRANCHCODE,OR_NO AS DOC_NO,A.DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN A.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,a.doc_type,branchcode,or_no) ,'' as 'Reprint label'
--FROM ORHDR AS A 
--JOIN ARS_DOC AS B ON A.DOC_TYPE = B.DOC_TYPE WHERE A.DOC_TYPE = B. DOC_TYPE and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----ARCM TRANSACTION--

--select BRANCHCODE,DOC_NO AS DOC_NO,'ARCM' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'ARCM',branchcode,doc_no) ,'' as 'Reprint label'
--FROM ARCMHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='ARCM'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----JV TRANSACTION--

--select BRANCHCODE,DOC_NO AS DOC_NO,'JV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN B.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'JV',branchcode,doc_no) ,'' as 'Reprint label'
--FROM GLTRHD 
--CROSS JOIN ARS_DOC AS B WHERE B.DOC_TYPE ='JV'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----ARDM TRANSACTION--

--select BRANCHCODE,DOC_NO AS DOC_NO,'ARDM' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN B.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'ARDM',branchcode,doc_no) ,'' as 'Reprint label'
--FROM ARDMHDR  
--CROSS JOIN ARS_DOC AS B WHERE B.DOC_TYPE ='ARDM' and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----PCV TRANSACTION--

--select BRANCHCODE,PCV_NO AS DOC_NO,'PCV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'PCV',branchcode,pcv_no) ,'' as 'Reprint label'
--FROM PCVHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='PCV'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----VSI TRANSACTION--

--select BRANCHCODE,VSI_NO AS DOC_NO,'VSI' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VSI',branchcode,vsi_no),'' as 'Reprint label' 
--FROM VSIHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VSI'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FARR TRANSACTION--

--select BRANCHCODE,RR_NO AS DOC_NO,'FARR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FARR',branchcode,rr_no),'' as 'Reprint label' 
--FROM FARRHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='FARR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FATR TRANSACTION--

--select BRANCHCODE,WT_NO AS DOC_NO,'FATR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FATR',branchcode,wt_no) ,'' as 'Reprint label'
--FROM FAWTHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='FATR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FARET TRANSACTION--

--select BRANCHCODE,DOC_NO AS DOC_NO,'FARET' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FARET',branchcode,doc_no) ,'' as 'Reprint label'
--FROM FARTHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='FARET'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----FACA TRANSACTION--

--select BRANCHCODE,FACA_NO AS DOC_NO,'FACA' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'FACA',branchcode,faca_no) ,'' as 'Reprint label'
--FROM FACAHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='FACA'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----PRC TRANSACTION--

--select BRANCHCODE,PR_NO AS DOC_NO,'PRC' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN D.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'PRC',branchcode,pr_no) ,'' as 'Reprint label'
--FROM PRCHDR AS A

--CROSS JOIN ARS_DOC AS D WHERE D.DOC_TYPE = 'PRC' and isnull(FORM_PRINT1,'') <> ''


--UNION ALL

----WOR TRANSACTION--

--select BRANCHCODE,FGES_NO AS DOC_NO,'WOR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN D.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'WOR',branchcode,FGES_NO) ,'' as 'Reprint label'
--FROM FGESHDR AS A

--CROSS JOIN ARS_DOC AS D WHERE D.DOC_TYPE = 'WOR' and isnull(FORM_PRINT1,'') <> ''


--UNION ALL

----VBS TRANSACTION--

--select BRANCHCODE,SI_NO AS DOC_NO,'VBS' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN D.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VBS',branchcode,SI_NO) ,'' as 'Reprint label'
--FROM CBSIHDR AS A

--CROSS JOIN ARS_DOC AS D WHERE D.DOC_TYPE = 'VBS' and isnull(FORM_PRINT1,'') <> ''



--UNION ALL

----CRT TRANSACTION--

--select BRANCHCODE,or_no AS DOC_NO,'CRT' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN D.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'CRT',branchcode,or_no) ,'' as 'Reprint label'
--FROM rcthdr AS A

--CROSS JOIN ARS_DOC AS D WHERE D.DOC_TYPE = 'CRT' and isnull(FORM_PRINT1,'') <> ''


--UNION ALL

----VSO TRANSACTION--

--select BRANCHCODE,vso_no AS DOC_NO,'VSO' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN D.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VSO',branchcode,vso_no) ,'' as 'Reprint label'
--FROM vsohdr AS A

--CROSS JOIN ARS_DOC AS D WHERE D.DOC_TYPE = 'VSO' and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----VDR TRANSACTION--

--select BRANCHCODE,vdr_no AS DOC_NO,'VDR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN D.DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VDR',branchcode,vdr_no) ,'' as 'Reprint label'
--FROM vdrhdr AS A

--CROSS JOIN ARS_DOC AS D WHERE D.DOC_TYPE = 'VDR' and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----VERR TRANSACTION--

--select BRANCHCODE,RR_NO AS DOC_NO,'VERR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VERR',branchcode,rr_no) ,'' as 'Reprint label'
--FROM VERRHDR
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VERR'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----VEST TRANSACTION--

--select BRANCHCODE,WT_NO AS DOC_NO,'VEST' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VEST',branchcode,wt_no) ,'' as 'Reprint label'
--FROM VEWTHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VEST'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----VEADJ TRANSACTION--

--select BRANCHCODE,ADJ_NO AS DOC_NO,'VEADJ' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VEADJ',branchcode,adj_no) ,'' as 'Reprint label'
--FROM VEDJHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VEADJ'and isnull(FORM_PRINT1,'') <> ''


--UNION ALL

----VESR TRANSACTION--

--select BRANCHCODE,SR_NO AS DOC_NO,'VESR' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VESR',branchcode,SR_NO) ,'' as 'Reprint label'
--FROM VESRHDR
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VESR'and isnull(FORM_PRINT1,'') <> ''


--UNION ALL

----VERTV TRANSACTION--

--select BRANCHCODE,RTV_NO AS DOC_NO,'VERTV' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VERTV',branchcode,RTV_NO) ,'' as 'Reprint label'
--FROM VERTVHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VERTV'and isnull(FORM_PRINT1,'') <> ''

----VJOS TRANSACTION--

--UNION ALL

--select BRANCHCODE,CJO_NO AS DOC_NO,'VJOS' AS DOC_TYPE,DOC_DESC,CASE WHEN ISNULL(TO_DOCTYPE,'') = '' THEN DOC_TYPE ELSE TO_DOCTYPE END AS TO_DOCTYPE,
--no_reprints = DBO.fnNoReprint(no_reprints,'VJOS',branchcode,CJO_NO) ,'' as 'Reprint label'
--FROM CJOSHDR 
--CROSS JOIN ARS_DOC WHERE DOC_TYPE ='VJOS'and isnull(FORM_PRINT1,'') <> ''

--UNION ALL

----ARDS TRANSACTION--

--select a.branchcode,a.ds_no as doc_no,'ARDS' as doc_type,z.doc_desc,case when isnull(to_doctype,'') = '' then z.doc_type else z.to_doctype end as to_doctype,
--no_reprints = DBO.fnNoReprint(null,'ARDS',branchcode,ds_no),'' as 'Reprint label'
--from ardshdr as a
--cross join ars_doc as z where z.doc_type = 'ARDS' and isnull(form_print1,'') <> '' 











GO


