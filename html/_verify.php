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
            <!-- <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p> -->
            <div class="row">
                <div class="col-4">Name</div>
                <div class="col-8" id="person_name"><?php echo !empty($person)? $person->nik: '' ?></div>
            </div>
            <div class="row">
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
