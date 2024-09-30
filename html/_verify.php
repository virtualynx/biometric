<div class="row mx-3 mb-3">
    <div class="col-12 card">
        <div class="card-body">
            <h5 class="card-title">Verify Biometrical Data (<span class="fp-device-status" class="">?</span>)</h5>
            <div class="row">
                <div class="col-12">
                    <div id="fingerprint-thumb" class="form-row justify-content-center">
                        <div 
                            id="fingerprint-verify" 
                            class="fingerprints col-4 text-center"
                        >
                            <span class="icon icon-fp" title=""></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- <div class="row form-row mx-3 mt-3 justify-content-center">
                <div class="col-12">
                    <button class="btn btn-primary btn-block my-sec-bg my-text-button py-1" type="button" onclick="beginVerify()">Start</button>
                </div>
            </div> -->
        </div>
    </div>

    <div class="col-12 card mt-3">
        <div class="card-body">
            <div class="row">
                <div class="col-12 text-right">
                    <button type="button" class="close" aria-label="Close" onclick="clearVerifyData()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div id="verify_not_found_label" class="row d-none">
                <div class="col-12 text-center">
                    <h2 class="form-label text-danger">Data Not Found !!</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-12 text-center">
                    <img id="verify_photo" src="./res/icons/icons8-photo-gallery-100.png" alt="" />
                </div>
            </div>
            <div class="row">
                <div class="col-4">Name</div>
                <div class="col-8" id="verify_name"><?php echo !empty($person)? $person->nik: '' ?></div>
            </div>
            <div class="row">
                <div class="col-4">NIK</div>
                <div class="col-8" id="verify_nik"><?php echo !empty($person)? $person->nik: '' ?></div>
            </div>
            <div class="row">
                <div class="col-4">Address</div>
                <div class="col-8" id="verify_address"><?php echo !empty($person)? $person->address: '' ?></div>
            </div>
            <div class="row">
                <div class="col-4">District</div>
                <div class="col-8" id="verify_district"><?php echo !empty($person)? $person->village: '' ?></div>
            </div>
        </div>
    </div>
</div>
