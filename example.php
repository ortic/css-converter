<?php

include __DIR__ . '/vendor/autoload.php';

$cssContent = <<<EOF
@charset "utf-8";

@font-face {
  font-family: "CrassRoots";
  src: url("../media/cr.ttf")
}

html, body {
    font-size: 1.6em
}

html p {
    margin-bottom: 10px;
    margin-top: 10px;
}

@media print {
    #logo {
        hidden: print;
    }
    body #footer {
        height: 50px;
        background: white;
    }
    @font-face {
        font-family: "CrassRoots";
    }

}

@keyframes mymove {
    from { top: 0px; }
    to { top: 200px; }
}
EOF;

$cssConverter = new \Ortic\CssConverter\CssConverter($cssContent);
$tree = $cssConverter->getTree();
print_r($tree);
