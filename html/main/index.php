<!doctype html>
<html lang="en">

<?php
require_once(dirname(__FILE__) . "/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/EnvFileModel.php");

use biometric\src\core\models\PersonModel;
use biometric\src\core\models\EnvFileModel;

$env = new EnvFileModel();
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

        .icon-indexfinger-not-enrolled {
            background-image: url("./res/icons/icons8-index-finger-50.png");
        }

        .icon-indexfinger-enrolled {
            background-image: url("./res/icons/icons8-index-finger-50-green.png");
        }

        .icon-thumb-not-enrolled {
            background-image: url("./res/icons/icons8-thumb-50.png");
        }

        .icon-thumb-enrolled {
            background-image: url("./res/icons/icons8-thumb-50-green.png");
        }

        .icon-fp {
            background-image: url("./res/icons/icons8-fingerprint-50.png");
        }

        .icon-fp-scanning {
            background-image: url("./res/icons/icons8-fingerprint-50-blue.png");
        }

        .icon-fp-scanned {
            background-image: url("./res/icons/icons8-fingerprint-50-green.png");
        }

        @keyframes blink-index-finger {
            from {
                background-image: url("./res/svg/indexfinger_not_enrolled.svg");
            }

            to {
                background-image: url("./res/svg/indexfinger-anim.svg");
            }
        }

        @keyframes blink-middle-finger {
            from {
                background-image: url("./res/svg/middlefinger_not_enrolled.svg");
            }

            to {
                background-image: url("./res/svg/middlefinger-anim.svg");
            }
        }

        body {
            background: rgb(213, 34, 34);
            background: linear-gradient(166deg, rgba(213, 34, 34, 1) 35%, rgba(135, 0, 0, 0.07326680672268904) 100%);
            background-repeat: no-repeat;
            background-position: center;
            height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen",
                "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue",
                sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;

        }

        .nav-tabs .nav-link.active,
        .nav-tabs .nav-item.show .nav-link {
            color: #914F1E
        }

        .container {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 5px 10px 15px;
        }
        .nav-tabs {
            border-bottom: none;
        }

        .nav-tabs .nav-link.active, .nav-tabs .nav-item.show .nav-link {
            border: none;
        }

        .nav-item .nav-link {
            color: #914F1E;
        }

        .card {
            border: none;
        }

        .btn-primary {
            background-color: #e31616;
            border: none;
            border-radius: 6px;
        }

        .btn-primary:hover {
            background-color: rgb(213, 34, 34, 0.8);
            border: none;
            border-radius: 6px; 
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
                        <a class="nav-link active" href="#register" role="tab" data-toggle="tab" onclick="fpAPi.onSamplesAcquired = ()=>{}">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#verify" role="tab" data-toggle="tab" onclick="initVerifyFingerprint()">Verify</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content">

            <div role="tabpanel" class="tab-pane fade in active show" id="register">
                <?php include_once('_tab_register.php'); ?>
            </div>

            <div role="tabpanel" class="tab-pane fade" id="verify">
                <?php include_once('_tab_verify.php'); ?>
            </div>
        </div>
    </div>
</body>

<script>
    const fingerprintTypeCount = 2;
    const fingerprintSampleCount = 4;
    const fpAPi = new Fingerprint.WebApi;
    const noPhotoIcon = './res/icons/icons8-photo-gallery-100.png';
    const cameraDefaultWidth = 640;
    const cameraDefaultHeight = 480;
    var fmdArray = [];
    var currentScanIndex = -1;
    var cameraStream = null;

    $(document).ready(function() {
        setInterval(fingerprintDetector_callback, 2000);

        fetchPersonList();

        <?php
        $pm = new PersonModel();
        if (!empty($_GET['nik'])) {
            $person = $pm->get($_GET['nik']);
        ?>
            $('[name="manual_input_register"]').val("<?php echo $_GET['nik'] ?>");
            pullPersonByNik("<?php echo $_GET['nik'] ?>");

            $('#button_pull_from_queue').attr('disabled', true);
            $('[name="manual_input_register"]').attr('readonly', true);
            $('#button_clear_register_profile').attr('disabled', true);
        <?php
        } else {
        ?>
            let queueId = localStorage.getItem("queue_id");
            if (queueId) {
                pullFromQueue();
            }
        <?php
        }
        ?>

        renderCardRowRegisterButtons();
        renderCardRowTakePhoto();

        getCameraList();
    });

    function xhrErrorCallback(xhr, status, error) {
        console.log('xhr', xhr);
        console.log('status', status);
        console.log('error', error);
    }

    function fingerprintDetector_callback() {
        fpAPi.enumerateDevices()
            .then((devices) => {
                $('.fp-device-status').removeClass('text-success');
                if (devices.length > 0) {
                    $('.fp-device-status').removeClass('text-danger');
                    $('.fp-device-status').addClass('text-success');
                    $('.fp-device-status').html("Device connected");
                } else {
                    $('.fp-device-status').removeClass('text-success');
                    $('.fp-device-status').addClass('text-danger');
                    $('.fp-device-status').html("Device not detected");
                }
            });
    }

    function fetchPersonList() {
        // $.ajax({
        //     type: "GET",
        //     url: "./api/queue/list.php",
        //     data: {},
        //     dataType: "json",
        //     success: (res) => {
        //         // console.log('queue/list', res);
        //         if(res && res.length > 0){
        //             $('#datalist_manual').html('');
        //             res.forEach(a => {
        //                 if(a.status == 'PENDING'){
        //                     $('#datalist_manual').append(`
        //                         <option value="${a.nik}">
        //                             ${a.person?.name} (${a.nik})
        //                         </option>
        //                     `);
        //                 }
        //             });
        //             $('#datalist_manual').trigger("change");
        //         }
        //     },
        //     error: (xhr, status, error) => {
        //         // if(xhr.responseText != ''){
        //         //     console.log('error', err);
        //         // }
        //     }
        // });

        $.ajax({
            type: "GET",
            url: "./api/person/list.php",
            data: {},
            dataType: "json",
            success: (res) => {
                console.log('person/list', res);
                if (res && res.length > 0) {
                    $('#datalist_manual').html('');
                    $('#datalist_verify').html('');
                    res.forEach(a => {
                        $('#datalist_manual').append(`
                            <option value="${a.nik}">
                                ${a.name} (${a.nik})
                            </option>
                        `);
                        $('#datalist_verify').append(`
                            <option value="${a.nik}">
                                ${a.name} (${a.nik})
                            </option>
                        `);
                    });
                    $('#datalist_manual').trigger("change");
                    $('#datalist_verify').trigger("change");
                }
            },
            error: (xhr, status, error) => {
                // if(xhr.responseText != ''){
                //     console.log('error', err);
                // }
            }
        });
    }

    function pullFromQueue() {
        $('#manual_input_register').val('');
        $('#manual_input_register').trigger('change');

        let queueId = localStorage.getItem("queue_id");

        if (queueId) {
            $.ajax({
                type: "POST",
                url: "./api/queue/status.php",
                data: {
                    queue_id: queueId
                },
                dataType: "json",
                success: (res) => {
                    // console.log('queue/status', res);
                    if (res.status) {
                        if (res.status == 'PENDING' || res.status == 'COMPLETED') {
                            localStorage.removeItem('queue_id');
                            localStorage.removeItem("is_from_queue");
                            pullFromQueue();
                        } else if (res.status == 'PULLED') {
                            localStorage.setItem("is_from_queue", true);
                            setRegisterProfile(res.queue.person);
                        }
                    } else {
                        localStorage.removeItem('queue_id');
                        localStorage.removeItem("is_from_queue");
                        pullFromQueue();
                    }
                },
                error: xhrErrorCallback
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
                // console.log('queue/pull', res);
                localStorage.setItem("queue_id", res.queue_id);
                localStorage.setItem("is_from_queue", true);
                setRegisterProfile(res.person);
            },
            error: (xhr, status, error) => {
                if (xhr.responseText != 'Data not found') {
                    console.log('xhr', xhr);
                    console.log('status', status);
                    console.log('error', error);
                }
            }
        });
    }

    function pullPersonByNik(nik) {
        if (
            !nik ||
            nik?.length == 0
            // || (0 < nik?.length && nik?.length < 16)
        ) {
            return;
        }

        // console.log('pullPersonByNik - nik', nik);

        $.ajax({
            type: "POST",
            url: "./api/queue/pull.php",
            data: {
                nik: nik
            },
            dataType: "json",
            success: (res) => {
                // console.log('queue/pull', res);
                localStorage.setItem("queue_id", res.queue_id);
                localStorage.setItem("is_from_queue", true);
                setRegisterProfile(res.person);
            },
            error: (xhr, status, error) => {
                if (xhr.responseText == 'Data not found') {
                    $.ajax({
                        type: "POST",
                        url: "./api/person/getinfo.php",
                        data: {
                            nik: nik,
                            without_photo: true
                        },
                        dataType: "json",
                        success: (res) => {
                            // console.log('person/getinfo', res);
                            localStorage.setItem("is_from_queue", false);
                            setRegisterProfile(res);
                            fetchProfilePhoto(res);
                        },
                        error: (xhr, status, error) => {
                            if (xhr.responseText == 'Data not found') {
                                localStorage.setItem("is_from_queue", false);
                                setRegisterProfile({
                                    photos: [],
                                    name: '',
                                    nik: nik,
                                    address: '',
                                    village: '',
                                    biometric_status: {
                                        fingerprint: ''
                                    }
                                });
                            }
                        }
                    });
                } else {
                    console.log('error', error);
                }
            }
        });
    }

    function renderCardRowRegisterButtons() {
        let nik = $('#person_nik').html().trim();

        if (nik.length == 0) {
            $('#card_row_register_buttons').removeClass('d-none');
            $('#card_row_register_another').addClass('d-none');
        } else {
            $('#card_row_register_buttons').addClass('d-none');
            $('#card_row_register_another').removeClass('d-none');
        }
    }

    function renderCardRowTakePhoto() {
        let nik = $('#person_nik').html().trim();
        let photoSrc = $('#person_photo').attr('src');

        $('#card_row_take_photo').addClass('d-none');
        if (nik.length > 0 && photoSrc == noPhotoIcon) {
            $('#card_row_take_photo').removeClass('d-none');
        } else {
            $('#card_row_take_photo').addClass('d-none');
        }
    }

    function openModalTakeFingerprint() {
        let nik = $('#person_nik').html()?.trim();

        if (!nik) {
            nik = $('#manual_input_register').val()?.trim();
        }

        if (!nik) {
            alert('No Person is selected');
            return;
        }

        $('#modalFingerprint').modal('show');
        initTakeFingerprints();
        beginCapture();
    }

    function initTakeFingerprints() {
        fpAPi.onSamplesAcquired = onSamplesAcquired_callback;

        $('#btn-fingerprint-begin').removeClass('d-none');
        $('#btn-fingerprint-save').addClass('d-none');

        let col_width = 12 / fingerprintSampleCount;

        let counter = 1;
        //index
        $('#fingerprint-index').html('');
        for (let a = 1; a <= fingerprintSampleCount; a++) {
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
        for (let a = 1; a <= fingerprintSampleCount; a++) {
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

    function beginCapture() {
        if (currentScanIndex > 0) return;

        fpAPi.startAcquisition(Fingerprint.SampleFormat.Intermediate, "")
            .then(function() {
                fmdArray = [];
                currentScanIndex = 1;
                $('#btn-fingerprint-begin').addClass('disabled');
                $(`[data-num=1]`).find('span').removeClass('icon-fp');
                $(`[data-num=1]`).find('span').addClass('icon-fp-scanning');
            }, function(error) {
                console.log('startCapture - error', error.message);
            });
    }

    function stopCapture() {
        if (currentScanIndex == -1) return;

        fpAPi.stopAcquisition()
            .then(function() {
                currentScanIndex = -1;
                $('#btn-fingerprint-begin').removeClass('disabled');
                $('#btn-fingerprint-begin').removeClass('d-none');
                $('#btn-fingerprint-save').addClass('d-none');
            }, function(error) {
                console.log('stopCapture - error', error.message);
            });
    }

    function onSamplesAcquired_callback(e) {
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

        if (currentScanIndex > (fingerprintTypeCount * fingerprintSampleCount)) {
            saveFingerprints();

            return;
        }

        $(`[data-num=${currentScanIndex}]`).find('span').removeClass('icon-fp');
        $(`[data-num=${currentScanIndex}]`).find('span').addClass('icon-fp-scanning');
    }

    function saveFingerprints() {
        let nik = $('#person_nik').html().trim();

        if (!nik) {
            nik = $('#manual_input_register').val().trim();
        }

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
                if(res.status == 'success'){
                    $('#modalFingerprint').modal('hide');
                    $('#person_has_fingerprint').addClass('d-none');
                    alert("Fingerprint registration success");
                }else{
                    alert(`Failed: ${res.status}`);
                }
            },
            error: xhrErrorCallback
        });
    }

    function getCameraList() {
        $('#take_photo_cam_list').html('');

        navigator.mediaDevices.enumerateDevices()
            .then((mediaDevices) => {
                mediaDevices.forEach(device => {
                    // console.log('device', device);
                    if (device.kind === 'videoinput') {
                        $('#take_photo_cam_list').append(`
                        <option value="${device.deviceId}">${device.label}</option>
                    `);
                    }
                });
            })
            .catch((err1) => {
                console.log('err1', err1);
            });
    }

    function cameraChanged() {
        stopWebcam();
        playCamera();
    }

    function playCamera(width = cameraDefaultWidth, height = cameraDefaultHeight) {
        let cameraId = $('#take_photo_cam_list').val();
        // console.log('playCamera - cameraId', cameraId);
        $("video#webcam").bind("loadedmetadata", function() {
            const canvas = document.querySelector("canvas#webcam_canvas");
            canvas.setAttribute('width', this.videoWidth);
            canvas.setAttribute('height', this.videoHeight);
        });

        navigator.mediaDevices.getUserMedia({
            'audio': false,
            'video': {
                'deviceId': cameraId,
                'width': {
                    'min': width
                },
                'height': {
                    'min': height
                }
            }
        }).then(stream => {
            const videoElement = document.querySelector('video#webcam');
            // videoElement.setAttribute('width', width);
            // videoElement.setAttribute('height', height);
            videoElement.srcObject = stream;
        });
    }

    function openModalTakePhoto() {
        playCamera();

        $('#take_photo_cam').removeClass('d-none');
        $('#take_photo_captured').addClass('d-none');
        $('#modalPhoto').modal('show');
    }

    function takePhoto() {
        const video = document.querySelector('video#webcam');

        const canvas = document.querySelector("canvas#webcam_canvas");
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        let base64 = canvas.toDataURL('image/jpeg');

        $('#take_photo_captured').attr('src', base64);

        $('#take_photo_cam').addClass('d-none');
        $('#take_photo_captured').removeClass('d-none');
    }

    function savePhoto() {
        let base64 = $('#take_photo_captured').attr('src');
        if (base64?.length == 0) {
            alert('Take photo before saving');
            return;
        }

        let nik = $('#person_nik').html();

        $.ajax({
            type: "POST",
            url: "<?php echo $env->get('FILE_STORAGE_HOST') ?>/api/person/upload_photo.php",
            data: {
                nik: nik,
                photo_type: 'biometric',
                photo: base64,
                is_base64: true,
                filename: `${nik}.jpeg`
            },
            // processData: false,
            dataType: "text",
            success: (res) => {
                pullPersonByNik(nik);
                $('#modalPhoto').modal('hide');
                alert('Biometric photo success');
            },
            error: xhrErrorCallback
        });
    }

    function stopWebcam() {
        const video = document.querySelector('video#webcam');
        video.srcObject.getTracks().forEach(track => track.stop());
    }

    function deleteProfilePhoto() {
        let nik = $('#person_nik').html();

        if (nik?.length == 0) return;

        if (confirm("Delete photo ?") == true) {
            $.ajax({
                type: "POST",
                url: "<?php echo $env->get('FILE_STORAGE_HOST') ?>/api/person/delete_photo.php",
                data: {
                    nik: nik,
                    photo_type: 'biometric'
                },
                // processData: false,
                dataType: "text",
                success: (res) => {
                    console.log(res);
                    if (res == 'success') {
                        $('#person_photo').attr('src', noPhotoIcon);
                    }
                },
                error: xhrErrorCallback
            });
        }
    }

    function setRegisterProfile(person) {
        // console.log('setRegisterProfile', person);

        // $('#person_photo').attr('src', person.photo!=null? person.photo: noPhotoIcon);
        $('#person_photo').attr('src', noPhotoIcon);
        $('#person_name').html(person.name);
        $('#person_nik').html(person.nik);
        $('#person_address').html(person.address);
        $('#person_district').html(person.village);

        fetchProfilePhoto(person, (res) => {
            if (res) {
                $('#person_photo').attr('src', res);
            }
            renderCardRowTakePhoto();
        });

        if (person.biometric_status.fingerprint == 'completed') {
            $('#person_has_fingerprint').removeClass('d-none');
        } else {
            if (!($('#person_has_fingerprint').hasClass('d-none'))) {
                $('#person_has_fingerprint').addClass('d-none');
            }
        }

        renderCardRowRegisterButtons();
    }

    function clearRegisterProfile() {
        $('#person_photo').attr('src', noPhotoIcon);
        $('#person_nik').html('');
        $('#person_name').html('');
        $('#person_address').html('');
        $('#person_district').html('');

        $('#manual_input_register').val('');

        if (!($('#person_has_fingerprint').hasClass('d-none'))) {
            $('#person_has_fingerprint').addClass('d-none');
        }

        renderCardRowRegisterButtons();
        renderCardRowTakePhoto();
    }

    function reEnqueue() {
        let queueId = localStorage.getItem("queue_id");
        if (queueId) {
            $.ajax({
                type: "POST",
                url: "./api/queue/re_enqueue.php",
                data: {
                    queue_id: queueId
                },
                dataType: "json",
                success: (res) => {
                    console.log('queue/re_enqueue', res);
                    localStorage.removeItem("queue_id");
                    localStorage.removeItem("is_from_queue");
                },
                error: xhrErrorCallback
            });
        }
    }

    function completeRegistration() {
        let queueId = localStorage.getItem("queue_id");
        if (queueId) {
            $.ajax({
                type: "POST",
                url: "./api/queue/complete_queue.php",
                data: {
                    queue_id: queueId
                },
                dataType: "json",
                success: (res) => {
                    console.log('queue/complete_queue', res);
                    localStorage.removeItem("queue_id");
                    completeRegistration_redirectOrAlert();
                },
                error: xhrErrorCallback
            });
        } else {
            completeRegistration_redirectOrAlert();
        }
    }

    function completeRegistration_redirectOrAlert() {
        <?php
        if (!empty($_GET['redirect'])) {
        ?>
            let encodedParams = encodeURI(`status=success`);
            window.location.href = `<?php echo $_GET['redirect'] ?>?${encodedParams}`;
        <?php
        } else {
        ?>
            clearRegisterProfile();
            fetchPersonList();
            alert('Biometric registration success');
        <?php
        }
        ?>
    }

    //verify sections
    function initVerifyFingerprint() {
        fpAPi.onSamplesAcquired = onSamplesAcquired_verify_callback;
        beginCaptureVerify();
    }

    function beginCaptureVerify() {
        fpAPi.startAcquisition(Fingerprint.SampleFormat.Intermediate, "")
            .then(function() {
                $('#fingerprint-verify').find('span').removeClass('icon-fp');
                $('#fingerprint-verify').find('span').addClass('icon-fp-scanning');
            }, function(error) {
                console.log('startCapture - error', error.message);
            });
    }

    function fetchVerifyProfile() {
        let nik = $('[name="datalist_verify_input"]').val()?.trim();
        if (nik?.length == 0) return;

        $.ajax({
            type: "POST",
            url: "./api/person/getinfo.php",
            data: {
                nik: nik,
                without_photo: true
            },
            dataType: "json",
            success: (res) => {
                // console.log('fetchVerifyProfile', res);
                if (res) {
                    setVerifyProfile(res);
                }
            },
            error: (xhr, status, error) => {
                if (xhr.responseText == 'Data not found') {
                    clearVerifyProfile();
                    $('#verify_not_found_label').removeClass('d-none');
                    setTimeout(() => {
                        $('#verify_not_found_label').addClass('d-none');
                    }, 2500);
                }
                console.log('xhr', xhr);
                console.log('status', status);
                console.log('error', error);
            }
        });
    }

    function setVerifyProfile(person) {
        // $('#verify_photo').attr('src', res.person.photo!=null? res.person.photo: noPhotoIcon);
        $('#verify_photo').attr('src', noPhotoIcon);
        fetchProfilePhoto(person, (res) => {
            if (res) {
                $('#verify_photo').attr('src', res);
            }
        });
        $('#verify_nik').html(person.nik);
        $('#verify_name').html(person.name);
        $('#verify_address').html(person.address);
        $('#verify_district').html(person.village);
    }

    function clearVerifyProfile() {
        $('#verify_photo').attr('src', noPhotoIcon);
        $('#verify_nik').html('');
        $('#verify_name').html('');
        $('#verify_address').html('');
        $('#verify_district').html('');

        $('#verify_found_label').addClass('d-none');
        $('#verify_not_found_label').addClass('d-none');
    }

    function onSamplesAcquired_verify_callback(e) {
        console.log("onSamplesAcquired_verify", e);
        $('#fingerprint-verify').find('span').removeClass('icon-fp-scanning');
        $('#fingerprint-verify').find('span').addClass('icon-fp');

        // clearVerifyProfile();

        let samples = JSON.parse(e.samples);
        let fmd = samples[0].Data;
        let nik = $('[name="datalist_verify_input"]').val()?.trim();

        $.ajax({
            type: "POST",
            url: "./api/fingerprint/verify.php",
            data: {
                fmd: fmd,
                nik: nik
            },
            dataType: "json",
            success: (res) => {
                // console.log('onSamplesAcquired_verify_callback', res);
                
                $('#verify_found_label').addClass('d-none');
                $('#verify_not_found_label').addClass('d-none');

                $('#verify_match').addClass('d-none');
                $('#verify_not_match').addClass('d-none');

                if (res.person) {
                    // $('#verify_photo').attr('src', res.person.photo!=null? res.person.photo: noPhotoIcon);
                    fetchProfilePhoto(res.person, (res) => {
                        if (res) {
                            $('#verify_photo').attr('src', res);
                        }
                    });
                    $('#verify_nik').html(res.person.nik);
                    $('#verify_name').html(res.person.name);
                    $('#verify_address').html(res.person.address);
                    $('#verify_district').html(res.person.village);

                    let labelToShown = '';
                    if(nik.length == 0){
                        labelToShown = '#verify_found_label';
                    }else{
                        labelToShown = '#verify_match';
                    }

                    $(labelToShown).removeClass('d-none');
                    setTimeout(()=>{
                        $(labelToShown).addClass('d-none');
                    }, 7000);
                }else{
                    let labelToShown = '';
                    if(nik.length == 0){
                        $('#verify_photo').attr('src', noPhotoIcon);
                        labelToShown = '#verify_not_found_label';
                    }else{
                        labelToShown = '#verify_not_match';
                    }

                    $(labelToShown).removeClass('d-none');
                    setTimeout(()=>{
                        $(labelToShown).addClass('d-none');
                    }, 7000);
                }

                setTimeout(beginCaptureVerify, 500);
            },
            error: (xhr, status, error) => {
                if(xhr.responseText == 'No enrolled fingerprint data'){
                    alert('No enrolled fingerprint data');
                }
            }
        });
    }

    function fetchProfilePhoto(person, successCallback) {
        let profilePic = person.photos.find(a => a.type == 'biometric');
        if (profilePic) {
            $.ajax({
                type: "GET",
                url: "<?php echo $env->get('FILE_STORAGE_HOST') ?>/api/person/download_photo.php",
                data: {
                    nik: person.nik,
                    filename: profilePic.filename,
                    is_base64: true
                },
                dataType: "text",
                success: successCallback,
                error: xhrErrorCallback
            });
        } else {
            renderCardRowTakePhoto();
        }
    }
</script>

</html>