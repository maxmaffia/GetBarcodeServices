<?php

return [
    'categorie' => [
        'table' => 'dbo.categorie',
        'fields' => ['CatCode', 'CatDescr', 'CatIva', 'CatMisure', 'CatReportComm'],
        'default_sort' => 'CatCode',
    ],
    'tipi' => [
        'table' => 'dbo.tipi',
        'fields' => ['TipoCode', 'TipoDescr'],
        'default_sort' => 'TipoCode',
    ],
    'reparti' => [
        'table' => 'dbo.reparti',
        'fields' => ['RepCode', 'RepDescr', 'RepServizi'],
        'default_sort' => 'RepCode',
    ],
    'stagioni' => [
        'table' => 'dbo.stagioni',
        'fields' => ['StaCode', 'StaDescr'],
        'default_sort' => 'StaCode',
    ],
    'pellami' => [
        'table' => 'dbo.pellami',
        'fields' => ['PelCode', 'PelDescr'],
        'default_sort' => 'PelCode',
    ],
    'fornitori' => [
        'table' => 'dbo.fornitori',
        'fields' => [
            'ForCode', 'ForDescr', 'ForIndir', 'ForCitta', 'ForCap', 'ForProv', 'ForPiva', 'ForTelef',
            'ForFax', 'ForAgente', 'ForAgtel', 'ForBanca', 'ForPorto', 'ForPagam', 'ForNote', 'ForEMail',
            'ForSede', 'ForIndir1', 'ForCitta1', 'ForCap1', 'ForProv1', 'ForTelef1', 'ForFax1', 'ForCodeLong',
            'ForAliquota', 'ForTipo', 'ForValuta', 'ForCodFisc', 'ForCodISO', 'ForLeadTime', 'ForBancaFiliale',
            'ForBancaAbi', 'ForBancaCab', 'ForBancaCC', 'ForBancaCIN', 'ForBancaCINEur', 'ForBancaPaese',
            'ForBancaBIC', 'ForHomePage', 'ForBancaIban', 'ForCodiceDestinatario', 'ForPEC'
        ],
        'default_sort' => 'ForCode',
    ],
    'marchi' => [
        'table' => 'dbo.Marchi',
        'fields' => [
            'MarCode', 'Mardescr', 'MarScAcq1', 'MarScAcq2', 'MarScAcq3', 'MarRicarico1', 'MarScontoAss1',
            'MarRicarico2', 'MarScontoAss2', 'MarRicarico3', 'MarScontoAss3', 'MarRicarico4', 'MarScontoAss4',
            'MarRicarico5', 'MarScontoAss5', 'MarFornitore'
        ],
        'default_sort' => 'MarCode',
    ],
    'discipline' => [
        'table' => 'dbo.discipline',
        'fields' => ['DisCode', 'DisDescr'],
        'default_sort' => 'DisCode',
    ],
    'numerazioni' => [
        'table' => 'dbo.numerazioni',
        'fields' => [
            'NumCode', 'NumTaglie', 'NumDescr', 'NumCodeGestionale', 'NumTaglieGestionale', 'NumTipo'
        ],
        'default_sort' => 'NumCode',
    ],
    'modelli' => [
        'table' => 'dbo.modelli',
        'fields' => [
            'ModArticolo', 'ModDescr', 'ModForn', 'ModMar', 'ModStag', 'ModPro', 'ModCat', 'ModAltezza',
            'ModDis', 'ModPel', 'ModCol', 'ModPrzacq', 'ModPrzven1', 'ModPrzven2', 'ModPrzven3', 'ModPrzven4',
            'ModPrzven5', 'ModNumer', 'ModGiacqta', 'ModOrdqta', 'ModAcqqta', 'ModVendqta', 'ModTotGiac',
            'ModTotOrd', 'ModTotAcq', 'ModTotVen', 'ModTotTre', 'ModTotTru', 'ModValAcq', 'ModValVen',
            'ModValTre', 'ModValTru', 'ModLastAcq', 'ModLastVen', 'ModLastChk', 'ModSconto', 'ModSconto2',
            'ModSconto3', 'ModImage', 'ModNote', 'ModColForn', 'ModScVen1', 'ModScVen2', 'ModScVen3',
            'ModScVen4', 'ModScVen5', 'ModDataInserimento', 'ModLastChange', 'ModDataVarListino', 'ModTotOrdRi',
            'ModTotConRi', 'ModLastTre', 'ModLastTru', 'ModFirstAcq', 'ModFirstVen', 'ModNoSize',
            'ModIndicatoreFasciaPrezzo', 'ModStatus', 'ModCommissioni'
        ],
        'default_sort' => 'ModArticolo',
    ],    'modcorris_barcode' => [
        'table' => 'dbo.ModCorris_Barcode',
        'fields' => [
            'ModCorriBarcode', 'ModCorriArticolo', 'ModCorriPosTaglia', 'ModCorriTipoBarcode',
            'ModCorriAttr01', 'ModCorriAttr02', 'ModCorriAttr03'
        ],
        'default_sort' => 'ModCorriBarcode',
    ],];
