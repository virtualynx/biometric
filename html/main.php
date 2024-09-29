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
                        <a class="nav-link" href="#verify" role="tab" data-toggle="tab" onclick="initVerifyFingerprint()">Verify</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content">

            <div role="tabpanel" class="tab-pane fade in active show" id="register">
                <?php include_once('_register.php'); ?>
            </div>

            <div role="tabpanel" class="tab-pane fade" id="verify">
                <?php include_once('_verify.php'); ?>
            </div>
        </div>
    </div>
</body>

<script>
    const fingerprintTypeCount = 2;
    const fingerprintSampleCount = 4;
    const fpAPi = new Fingerprint.WebApi;
    var fmdArray = [];
    var currentScanIndex = -1;
    const nik = '<?php echo !empty($_GET['nik'])? $_GET['nik']: '1234123412341234' ?>';

    $(document).ready(function() {
        setInterval(queuePuller_callback, 5000);
        setInterval(fingerprintDetector_callback, 2000);
    });

    function fingerprintDetector_callback(){
        fpAPi.enumerateDevices()
        .then((devices) => {
            $('.fp-device-status').removeClass('text-success');
            if(devices.length > 0){
                $('.fp-device-status').removeClass('text-danger');
                $('.fp-device-status').addClass('text-success');
                $('.fp-device-status').html("Device connected");
            }else{
                $('.fp-device-status').removeClass('text-success');
                $('.fp-device-status').addClass('text-danger');
                $('.fp-device-status').html("Device not detected");
            }
        });
    }

    function initTakeFingerprints(){
        fpAPi.onSamplesAcquired = onSamplesAcquired_callback;

        $('#btn-fingerprint-begin').removeClass('d-none');
        $('#btn-fingerprint-save').addClass('d-none');

        let col_width = 12 / fingerprintSampleCount;

        let counter = 1;
        //index
        $('#fingerprint-index').html('');
        for(let a=1; a <= fingerprintSampleCount; a++){
            $('#fingerprint-index').append(`
                <div 
                    id="fingerprint-index-${a}" 
                    class="fingerprints col-${col_width} text-center"
                    data-num="${counter++}"
                    data-finger-type="index"
                >
                    <span class="icon icon-fp" title=""></span>
                </div>
            `);
        }

        //thumb
        $('#fingerprint-thumb').html('');
        for(let a=1; a <= fingerprintSampleCount; a++){
            $('#fingerprint-thumb').append(`
                <div 
                    id="fingerprint-thumb-${a}" 
                    class="fingerprints col-${col_width} text-center"
                    data-num=${counter++}
                    data-finger-type="thumb"
                >
                    <span class="icon icon-fp" title=""></span>
                </div>
            `);
        }
    }

    function beginCapture(){
        if(currentScanIndex > 0)return;

        fpAPi.startAcquisition(Fingerprint.SampleFormat.Intermediate, "")
        .then(function () {
            fmdArray = [];
            currentScanIndex = 1;
            $('#btn-fingerprint-begin').addClass('disabled');
            $(`[data-num=1]`).find('span').removeClass('icon-fp');
            $(`[data-num=1]`).find('span').addClass('icon-fp-scanning');
        }, function (error) {
            console.log('startCapture - error', error.message);
        });
    }

    function stopCapture(){
        if(currentScanIndex == -1)return;

        fpAPi.stopAcquisition()
        .then(function () {
            currentScanIndex = -1;
            $('#btn-fingerprint-begin').removeClass('disabled');
            $('#btn-fingerprint-begin').removeClass('d-none');
            $('#btn-fingerprint-save').addClass('d-none');
        }, function (error) {
            console.log('stopCapture - error', error.message);
        });
    }

    function finishedCapture(){
        if(currentScanIndex == -1)return;

        fpAPi.stopAcquisition()
        .then(function () {
            currentScanIndex = -1;
            $('#btn-fingerprint-begin').removeClass('disabled');
            $('#btn-fingerprint-begin').addClass('d-none');
            $('#btn-fingerprint-save').removeClass('d-none');
        }, function (error) {
            console.log('finishedCapture - error', error.message);
        });
    }

    function onSamplesAcquired_callback(e){
        console.log("onSamplesAcquired", e);
        let samples = JSON.parse(e.samples);
        let fmd = samples[0].Data;

        let fingerType = $(`[data-num=${currentScanIndex}]`).attr('data-finger-type');
        fmdArray.push({
            fingerType: fingerType,
            fmd: fmd
        });

        $(`[data-num=${currentScanIndex}]`).find('span').removeClass('icon-fp-scanning');
        $(`[data-num=${currentScanIndex}]`).find('span').addClass('icon-fp-scanned');

        currentScanIndex++;

        if(currentScanIndex > (fingerprintTypeCount * fingerprintSampleCount)){
            finishedCapture();
        }

        $(`[data-num=${currentScanIndex}]`).find('span').removeClass('icon-fp');
        $(`[data-num=${currentScanIndex}]`).find('span').addClass('icon-fp-scanning');
    }

    function saveFingerprints(){
        $.ajax({
            type: "POST",
            url: "./api/fingerprint/enroll.php",
            data: {
                nik: nik,
                fmds: fmdArray
            },
            dataType: "json",
            success: (res) => {
                stopCapture();
            },
            error: (xhr, status, error) => {
                var err = eval("(" + xhr.responseText + ")");
                console.log('error', err);
            }
        });
    }

    function queuePuller_callback(){
        let queueId = localStorage.getItem("queue_id");

        if(queueId){
            $.ajax({
                type: "POST",
                url: "./api/queue/status.php",
                data: {
                    queue_id: queueId
                },
                dataType: "json",
                success: (res) => {
                    // console.log('queue/status', res);
                    if(res.status){
                        if(res.status !== 'PULLED'){
                            localStorage.removeItem('queue_id');
                        }else{
                            setProfile(res.queue);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    var err = eval("(" + xhr.responseText + ")");
                    console.log('error', err);
                }
            });

            return;
        }

        $.ajax({
            type: "POST",
            url: "./api/queue/pull.php",
            data: {
                prefix: 'BMT'
            },
            dataType: "json",
            success: (res) => {
                console.log('queue/pull', res);
                localStorage.setItem("queue_id", res.queue_id);
                setProfile(res);
            },
            error: () => {}
        });
    }

    function setProfile(queue){
        $('#person_name').html(queue.person.name);
        $('#person_nik').html(queue.nik);
        $('#person_address').html(queue.person.address);
        $('#person_district').html(queue.person.village);
    }

    function initVerifyFingerprint(){
        fpAPi.onSamplesAcquired = onSamplesAcquired_verify_callback;
        beginCaptureVerify();
    }

    function beginCaptureVerify(){
        fpAPi.startAcquisition(Fingerprint.SampleFormat.Intermediate, "")
        .then(function () {
            $('#fingerprint-verify').find('span').removeClass('icon-fp');
            $('#fingerprint-verify').find('span').addClass('icon-fp-scanning');
        }, function (error) {
            console.log('startCapture - error', error.message);
        });
    }

    function onSamplesAcquired_verify_callback(e){
        console.log("onSamplesAcquired_verify", e);
        $('#fingerprint-verify').find('span').removeClass('icon-fp-scanning');
        $('#fingerprint-verify').find('span').addClass('icon-fp');

        let samples = JSON.parse(e.samples);
        let fmd = samples[0].Data;
        
        $.ajax({
            type: "POST",
            url: "./api/fingerprint/verify.php",
            data: {
                fmd: fmd
            },
            dataType: "json",
            success: (res) => {
                setTimeout(beginCaptureVerify, 500);
            },
            error: (xhr, status, error) => {
                var err = eval("(" + xhr.responseText + ")");
                console.log('error', err);
            }
        });
    }
</script>
</html>