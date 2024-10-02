<div class="row mx-3 mb-3">
    <div class="col-12 card">
        <div class="card-body">
            <div id="card_row_register_buttons" class="row">
                <div class="col-4 text-center">
                    <button 
                        type="button" 
                        id="button_pull_from_queue"
                        class="btn btn-primary btn-block"
                        onclick="pullFromQueue()"
                    >Pull from Queue</button>
                </div>
                <div class="col-8">
                    <div class="form-group">
                        <label for="datalist_manual_input" class="form-label"><h5>Or, Manual Register</h5></label>
                        <input 
                            id="datalist_manual_input" 
                            class="form-control" 
                            name="manual_input_register" 
                            list="datalist_manual" 
                            onchange="pullPersonByNik(this.value)"
                            onfocus="this.value=''"
                            placeholder="Search NIK / Name ..."
                        >
                        <datalist id="datalist_manual"></datalist>
                    </div>
                </div>
            </div>

            <div id="card_row_register_another" class="row">
                <div class="col-12 text-center">
                    <button 
                        type="button" 
                        id="button_pull_from_queue"
                        class="btn btn-danger btn-block"
                        onclick="clearRegisterProfile(); reEnqueue(); $('#datalist_manual_input').val('');"
                    >Register Another Person</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mx-3 mb-3">
    <div class="col-12 card">
        <div class="card-body">
            <div class="row">
                <div class="col-12 text-right">
                    <button type="button" class="close" aria-label="Close" onclick="deleteProfilePhoto()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            
            <h5 class="card-title">Register Biometrical Data for <span id="person_name" class="text-primary"></span></h5>
            <!-- <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p> -->
            
            <div class="row">
                <div class="col-12 text-center">
                    <img 
                        id="person_photo" 
                        src="<?php echo !empty($person)? $person->photo: './res/icons/icons8-photo-gallery-100.png' ?>" 
                        alt="" 
                        style="max-width: 100%"
                    />
                </div>
            </div>
            <div id="card_row_take_photo" class="row mt-2">
                <!-- <div class="col-2"></div> -->
                <div class="col-12 px-5">
                    <button type="button" class="btn btn-primary btn-block" onclick="openModalTakePhoto()">Take Photo</button>
                </div>
                <!-- <div class="col-2"></div> -->
            </div>
            <div class="row mt-2">
                <div class="col-4">NIK</div>
                <div class="col-8" id="person_nik"><?php echo !empty($person)? $person->nik: '' ?></div>
            </div>
            <div class="row">
                <div class="col-4">Address</div>
                <div class="col-8" id="person_address"><?php echo !empty($person)? $person->address: '' ?></div>
            </div>
            <div class="row">
                <div class="col-4">District</div>
                <div class="col-8" id="person_district"><?php echo !empty($person)? $person->village: '' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row mx-3 mb-3">
    <div class="col-12 card">
        <div class="card-body">
            <div id="person_has_fingerprint" class="row mb-2 d-none">
                <div class="col-12">
                    <h4 class="text-success">This person already has Fingerprint data</h4>
                </div>
            </div>
            <h5 class="card-title">Fingerprint (<span class="fp-device-status" class="">?</span>)</h5>
            <div class="row mb-2">
                <div class="col-4 text-center">
                    <!-- <img src="" style="width: 400px; height: 300px"/> -->
                    <span class="icon" style="background-image: url('./res/icons/icons8-fingerprint-50.png')" title="not_enrolled"></span>
                </div>
                <div class="col-8">
                    <!-- <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#modalFingerprint" onclick="initTakeFingerprints()">Take Fingerprints</button> -->
                    <button type="button" class="btn btn-primary btn-block" onclick="openModalTakeFingerprint()">Take Fingerprints</button>
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
            <div class="row mb-2">
                <div class="col-12">
                    <button type="button" class="btn btn-success btn-block" onclick="completeRegistration()">Complete Registration</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!--Modal Photo-->
<section>
    <div class="modal fade" id="modalPhoto" data-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title my-text my-pri-color" id="createEnrollmentTitle">Take Photo</h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="stopWebcam()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mx-3 mb-3">
                        <div class="col-4">Camera List</div>
                        <div class="col-8">
                            <select id="take_photo_cam_list" onchange="cameraChanged()"></select>
                        </div>
                    </div>
                    <div class="row mx-3">
                        <div class="col-12">
                            <div id="take_photo_cam" style="max-width: 100%">
                                <video id="webcam" autoplay playsinline style="width: 100%"></video>
                                <canvas id="webcam_canvas" class="d-none" style="width: 100%"></canvas>
                            </div>
                            <img id="take_photo_captured" src="" style="max-width: 100%"/>
                        </div>
                    </div>
                    <div class="row mx-3 mt-2">
                        <div class="col-6">
                            <button class="btn btn-primary my-text8 btn-outline-danger border-0" type="button" onclick="takePhoto()">Take Photo</button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-success my-text8 btn-outline-danger border-0" type="button" onclick="savePhoto()">Save</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="form-row">
                        <div class="col">
                            <button class="btn btn-secondary my-text8 btn-outline-danger border-0" type="button" data-dismiss="modal" onclick="stopWebcam()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!--Modal Fingerprint-->
<section>
    <div class="modal fade" id="modalFingerprint" data-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title my-text my-pri-color" id="createEnrollmentTitle">Take Fingerprints</h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="stopCapture()">
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

                        <!-- <div class="form-row m-3 mt-md-5 justify-content-center">
                            <div class="col-12">
                                <button id="btn-fingerprint-begin" class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="submit" onclick="beginCapture()">Start</button>
                            </div>
                            <div class="col-12">
                                <button id="btn-fingerprint-save" class="btn btn-success btn-block my-sec-bg my-text-button py-1 d-none" type="submit" onclick="saveFingerprints()">Save</button>
                            </div>
                        </div> -->
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
