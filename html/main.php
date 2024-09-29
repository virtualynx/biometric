<!doctype html>
<html lang="en">

<?php
    require_once(dirname(__FILE__)."/../src/core/models/PersonModel.php");

    use biometric\src\core\models\PersonModel;

    $pm = new PersonModel();
    $person = null;
    if(!empty($_GET['nik'])){
        $person = $pm->get($_GET['nik']);
    }
?>

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="./src/css/bootstrap.css">
    <link rel="stylesheet" href="./src/css/custom2.css">

    <style>    
        @font-face {
            font-family: philosopher;
            src: url('./res/webfonts/philosopher-regular.woff2') format('woff2'),
                url('./res/webfonts/philosopher-regular.woff') format('woff');
            font-weight: normal;
        }

        @font-face {
            font-family: philosopher;
            src: url('./res/webfonts/philosopher-bold.woff2') format('woff2'),
                url('./res/webfonts/philosopher-bold.woff') format('woff');
            font-weight: bold;
        }

        @font-face {
            font-family: philosopher;
            src: url('./res/webfonts/philosopher-italic.woff2') format('woff2'),
                url('./res/webfonts/philosopher-italic.woff') format('woff');
            font-weight: normal;
            font-style: italic;
        }

        @font-face {
            font-family: philosopher;
            src: url('./res/webfonts/philosopher-bolditalic.woff2') format('woff2'),
                url('./res/webfonts/philosopher-bolditalic.woff') format('woff');
            font-weight: bold;
            font-style: italic;
        }

        .icon-indexfinger-not-enrolled{
            background-image: url("./res/icons/icons8-index-finger-50.png");
        }

        .icon-indexfinger-enrolled{
            background-image: url("./res/icons/icons8-index-finger-50-green.png");
        }

        .icon-thumb-not-enrolled{
            background-image: url("./res/icons/icons8-thumb-50.png");
        }

        .icon-thumb-enrolled{
            background-image: url("./res/icons/icons8-thumb-50-green.png");
        }

        .icon-fp{
            background-image: url("./res/icons/icons8-fingerprint-50.png");
        }

        .icon-fp-scanning{
            background-image: url("./res/icons/icons8-fingerprint-50-blue.png");
        }

        .icon-fp-scanned{
            background-image: url("./res/icons/icons8-fingerprint-50-green.png");
        }

        @keyframes blink-index-finger{
            from{
                background-image: url("./res/svg/indexfinger_not_enrolled.svg");
            }

            to{
                background-image: url("./res/svg/indexfinger-anim.svg");
            }
        }

        @keyframes blink-middle-finger{
            from{
                background-image: url("./res/svg/middlefinger_not_enrolled.svg");
            }

            to{
                background-image: url("./res/svg/middlefinger-anim.svg");
            }
        }
    </style>

    <title>Biometric</title>
</head>

<body>
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="./src/js/jquery-3.5.0.min.js"></script>
    <script src="./src/js/bootstrap.bundle.js"></script>
    <script src="./src/js/es6-shim.js"></script>
    <script src="./src/js/websdk.client.bundle.min.js"></script>
    <script src="./src/js/fingerprint.sdk.min.js"></script>
    <!-- <script src="./src/js/custom3.js"></script> -->

    <div class="container">
        <div class="row mx-3 mt-5 mb-3">
            <div class="col-12 text-center">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" href="#register" role="tab" data-toggle="tab">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#verify" role="tab" data-toggle="tab">Verify</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane fade in active show" id="register">
                <?php include_once('_register.php'); ?>
            </div>

            <div role="tabpanel" class="tab-pane fade" id="verify">
                Verify
            </div>
        </div>
    </div>
</body>

</html>