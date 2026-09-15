SELECT nokontrak, nama, IIF(kdprd='', '51',kdprd) as kdprd, pokpby, kdloc, lb_sekon, IIF(gunadeb='','3',gunadeb) as gunadeb from TOFLMB where kdprd<>'30' and stsrec<>'D'

SELECT nokontrak, kdprd, pokpby, IIF(osmdlc<0,1,osmdlc) as osmdlc, IIF(colbaru='','5',colbaru) as colbaru, IIF(haritgkmdl>haritgkmgn,haritgkmdl, haritgkmgn) as tgkhari, iif(tgkmdl<0,1,tgkmdl) as tgkmdl, tglwo, stsrec, stsacc, tgleff, tglexp, periode FROM TOFLMBEOM where periode between '202001' and '202312' and kdprd<>'30' and stsrec not in ('D','L')

SELECT \* FROM SETUPLOAN

SELECT nokontrak, noreg, urut,jnsjamin, catatan, nomtaksasi, IIF(nomlikuid=0,nomtaksasi,nomlikuid) as nominal_penjualan, tgltaks2, 1 as is_active FROM TOFJAMIN where nokontrak<>''
