<!doctype html>
<html lang="en">

<?php
    $base_url = getenv('BASE_URL');
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
        <div id="controls" class="row justify-content-center mx-5 mx-sm-0 mx-lg-5">
            <div class="col-sm mb-2 ml-sm-5">
                <button id="createEnrollmentButton" type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#createEnrollment" onclick="beginEnrollment()">Create Enrollment</button>
            </div>
            <div class="col-sm mb-2 mr-sm-5">
                <button id="verifyIdentityButton" type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#verifyIdentity" onclick="beginIdentification()">Verify Identity</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row mx-3 mb-3">
            <div class="col-12 card">
                <div class="card-body">
                    <h5 class="card-title">Register Biometrical Data for </h5>
                    <!-- <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p> -->
                    <div class="row">
                        <div class="col-4">NIK</div>
                        <div class="col-8">(NIK)</div>
                    </div>
                    <div class="row">
                        <div class="col-4">Address</div>
                        <div class="col-8">(Address)</div>
                    </div>
                    <div class="row">
                        <div class="col-4">District</div>
                        <div class="col-8">(District)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mx-3 mb-3">
            <div class="col-12 card">
                <div class="card-body">
                    <h5 class="card-title">Fingerprint (<span id="fingerprint_status" class="">?</span>)</h5>
                    <div class="row mb-2">
                        <div class="col-4 text-center">
                            <!-- <img src="" style="width: 400px; height: 300px"/> -->
                            <span class="icon" style="background-image: url('./res/icons/icons8-fingerprint-50.png')" title="not_enrolled"></span>
                        </div>
                        <div class="col-8">
                            <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#modalFingerprint" onclick="initTakeFingerprints()">Take Fingerprints</button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mx-3 mb-3">
            <div class="col-12 card">
                <div class="card-body">
                    <h5 class="card-title">Photo</h5>
                    <div class="row mb-2">
                        <div class="col-12 text-center">
                            <img src="./res/icons/icons8-photo-gallery-100.png" style="width: 400px; height: 300px"/>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#createEnrollment" onclick="takePhoto()">Take Photo</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

<!--Modal Photo-->
<section>
</section>

<!--Modal Fingerprint-->
<section>
    <div class="modal fade" id="modalFingerprint" data-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title my-text my-pri-color" id="createEnrollmentTitle">Take Fingerprints</h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="#" onsubmit="return false">
                        <div class="form-row mt-1">
                            <div class="col-2 text-center">
                                <span class="icon icon-indexfinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div class="col-10">
                                <p class="my-text7 my-pri-color mt-3">Capture Index Finger (Right Hand)</p>
                            </div>
                        </div>
                        <div id="fingerprint-index" class="form-row justify-content-center mx-3">
                        </div>

                        <div class="form-row mt-3">
                            <div class="col-2 text-center">
                                <span class="icon icon-thumb-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div class="col-10">
                                <p class="my-text7 my-pri-color mt-3">Capture Thumb Finger (Right Hand)</p>
                            </div>
                        </div>
                        <div id="fingerprint-thumb" class="form-row justify-content-center">
                        </div>

                        <div class="form-row m-3 mt-md-5 justify-content-center">
                            <div class="col-12">
                                <button id="btn-fingerprint-begin" class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="submit" onclick="beginCapture()">Start</button>
                            </div>
                            <div class="col-12">
                                <button id="btn-fingerprint-save" class="btn btn-success btn-block my-sec-bg my-text-button py-1 d-none" type="submit" onclick="saveFingerprints()">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="form-row">
                        <div class="col">
                            <button class="btn btn-secondary my-text8 btn-outline-danger border-0" type="button" data-dismiss="modal" onclick="stopCapture()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section>
    <!--Create Enrolment Section-->
    <div class="modal fade" id="createEnrollment" data-backdrop="static" tabindex="-1" aria-labelledby="createEnrollmentTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title my-text my-pri-color" id="createEnrollmentTitle">Create Enrollment</h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="clearCapture()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="#" onsubmit="return false">
                        <div id="enrollmentStatusField" class="text-center">
                            <!--Enrollment Status will be displayed Here-->
                        </div>
                        <div class="form-row mt-3">
                            <div class="col mb-3 mb-md-0 text-center">
                                <label for="enrollReaderSelect" class="my-text7 my-pri-color">Choose Fingerprint Reader</label>
                                <select name="readerSelect" id="enrollReaderSelect" class="form-control" onclick="beginEnrollment()">
                                    <option selected>Select Fingerprint Reader</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row mt-2">
                            <div class="col mb-3 mb-md-0 text-center">
                                <label for="userID" class="my-text7 my-pri-color">Specify UserID</label>
                                <input id="userID" type="text" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row mt-1">
                            <div class="col text-center">
                                <p class="my-text7 my-pri-color mt-3">Capture Index Finger</p>
                            </div>
                        </div>
                        <div id="indexFingers" class="form-row justify-content-center">
                            <div id="indexfinger1" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-indexfinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div id="indexfinger2" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-indexfinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div id="indexfinger3" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-indexfinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div id="indexfinger4" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-indexfinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                        </div>
                        <div class="form-row mt-1">
                            <div class="col text-center">
                                <p class="my-text7 my-pri-color mt-5">Capture Middle Finger</p>
                            </div>
                        </div>
                        <div id="middleFingers" class="form-row justify-content-center">
                            <div id="middleFinger1" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-middlefinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div id="middleFinger2" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-middlefinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div id="middleFinger3" class="col mb-3 mb-md-0 text-center">
                                <span class="icon icon-middlefinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                            <div id="middleFinger4" class="col mb-3 mb-md-0 text-center" value="true">
                                <span class="icon icon-middlefinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                        </div>
                        <div class="form-row m-3 mt-md-5 justify-content-center">
                            <div class="col-4">
                                <button class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="submit" onclick="beginCapture()">Start Capture</button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="submit" onclick="serverEnroll()">Enroll</button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-secondary btn-outline-warning btn-block my-text-button py-1 border-0" type="button" onclick="clearCapture()">Clear</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="form-row">
                        <div class="col">
                            <button class="btn btn-secondary my-text8 btn-outline-danger border-0" type="button" data-dismiss="modal" onclick="clearCapture()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section>
    <!--Verify Identity Section-->
    <div id="verifyIdentity" class="modal fade" data-backdrop="static" tabindex="-1" aria-labelledby="verifyIdentityTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title my-text my-pri-color" id="verifyIdentityTitle">Identity Verification</h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="clearCapture()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="#" onsubmit="return false">
                        <div id="verifyIdentityStatusField" class="text-center">
                            <!--verifyIdentity Status will be displayed Here-->
                        </div>
                        <div class="form-row mt-3">
                            <div class="col mb-3 mb-md-0 text-center">
                                <label for="verifyReaderSelect" class="my-text7 my-pri-color">Choose Fingerprint Reader</label>
                                <select name="readerSelect" id="verifyReaderSelect" class="form-control" onclick="beginIdentification()">
                                    <option selected>Select Fingerprint Reader</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row mt-4">
                            <div class="col mb-md-0 text-center">
                                <label for="userIDVerify" class="my-text7 my-pri-color m-0">Specify UserID</label>
                                <input type="text" id="userIDVerify" class="form-control mt-1" required>
                            </div>
                        </div>
                        <div class="form-row mt-3">
                            <div class="col text-center">
                                <p class="my-text7 my-pri-color mt-1">Capture Verification Finger</p>
                            </div>
                        </div>
                        <div id="verificationFingers" class="form-row justify-content-center">
                            <div id="verificationFinger" class="col mb-md-0 text-center">
                                <span class="icon icon-indexfinger-not-enrolled" title="not_enrolled"></span>
                            </div>
                        </div>
                        <div class="form-row mt-3" id="userDetails">
                            <!--this is where user details will be displayed-->
                        </div>
                        <div class="form-row m-3 mt-md-5 justify-content-center">
                            <div class="col-4">
                                <button class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="submit" onclick="captureForIdentify()">Start Capture</button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="submit" onclick="serverIdentify()">Identify</button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-secondary btn-outline-warning btn-block my-text-button py-1 border-0" type="button" onclick="clearCapture()">Clear</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="form-row">
                        <div class="col">
                            <button class="btn btn-secondary my-text8 btn-outline-danger border-0" type="button" data-dismiss="modal" onclick="clearCapture()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    const fingerprintTypeCount = 2;
    const fingerprintSampleCount = 5;
    const fpAPi = new Fingerprint.WebApi;
    var fmdArray = [];
    var currentScanIndex = -1;
    const nik = '<?php echo !empty($_GET['nik'])? $_GET['nik']: '1234123412341234' ?>';

    $(document).ready(function() {
        setInterval(fingerprintDetector_callback, 2000);

        fpAPi.onSamplesAcquired = onSamplesAcquired_callback;
        // fpAPi.onQualityReported = function (e) {
        //     // Quality of sample acquired - Function triggered on every sample acquired
        //     console.log("onQualityReported", e);
        // }
    });

    function fingerprintDetector_callback(){
        fpAPi.enumerateDevices()
        .then((devices) => {
            $('#fingerprint_status').removeClass('text-success');
            if(devices.length > 0){
                $('#fingerprint_status').removeClass('text-danger');
                $('#fingerprint_status').addClass('text-success');
                $('#fingerprint_status').html("Device connected");
            }else{
                $('#fingerprint_status').removeClass('text-success');
                $('#fingerprint_status').addClass('text-danger');
                $('#fingerprint_status').html("Device not detected");
            }
        });
    }

    function initTakeFingerprints(){
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
            url: "./src/api/fingerprint/enroll.php",
            data: {
                nik: nik,
                fmds: fmdArray
            },
            dataType: "json",
            success: (resJson) => {
                stopCapture();
            },
            error: () => {}
        });
    }
</script>

</html>