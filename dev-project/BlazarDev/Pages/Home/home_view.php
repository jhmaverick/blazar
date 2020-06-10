<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8"/>

    <base href="<?= URL_BASE ?>"/>

    <link rel="stylesheet" type="text/css" href="<?= $home_css ?>"/>

    <script src="res/jquery.js"></script>

    <title></title>
</head>

<body>
<h1>Home</h1>
<h3><?= $blazar ?></h3>

<p><?= $msg ?></p>

<a href="home">Página Home</a><br>
<a href="db">Página DB</a><br>
<a href="pg_midias">Página Mídias</a><br>

<h3>Idiomas</h3>

Localidade atual: <?= locale_get_default() ?><br>
Texto por extenso com tradução para o português: <?= __('Hello %name%', ['%name%' => 'João']) ?><br>
Texto disponível em português e inglês: <?= $GLOBALS['translator']->trans('color') ?><br>
Texto disponível apenas em inglês: <?= __('car') ?><br>
Texto sem tradução: <?= __('Teste') ?><br>

<h3>APIs</h3>

<script>
    $.post("<?= URL_BASE ?>singleapi", {"method": "login"}, function (res) {
        $("body").append("Result SingleAPI: " + res + "<br>")
    });

    $.post("<?= URL_BASE ?>multiapi", {
        "multi_request": true, "params": JSON.stringify({
            "req-0": {"buscar": "", "acao": "teste1/acao1"},
            "req-1": {"buscar": "", "acao": "teste2/acao1"},
            "req-2": {"buscar": "ola", "acao": "teste1/acao2"}
        })
    }, function (res) {
        $("body").append("Result MultiAPI: " + JSON.stringify(res) + "<br>")
    });
</script>
</body>
</html>