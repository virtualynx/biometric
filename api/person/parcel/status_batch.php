<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");

use biometric\src\core\models\SubjectLandParcelModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$nikList = $input["nik_list"] ?? [];

if (empty($nikList) || !is_array($nikList)) {
    echo json_encode([
        "status" => "error",
        "message" => "nik_list is required"
    ]);
    exit;
}

try {
    $model = new SubjectLandParcelModel();
    $rows = $model->getShpSummaryByNikList($nikList);

    $result = [];
    foreach ($nikList as $nik) {
        $result[$nik] = [
            "total_parcels" => 0,
            "total_parcels_with_shp" => 0,
            "total_area_declared" => 0,
        ];
    }

    foreach ($rows as $row) {
        $nik = is_array($row) ? $row["nik"] : $row->nik;
        $totalParcels = is_array($row)
            ? (int) $row["total_parcels"]
            : (int) $row->total_parcels;
        $totalParcelsWithShp = is_array($row)
            ? (int) $row["total_parcels_with_shp"]
            : (int) $row->total_parcels_with_shp;
        $totalAreaDeclared = is_array($row)
            ? (float) $row["total_area_declared"]
            : (float) $row->total_area_declared;

        $result[$nik] = [
            "total_parcels" => $totalParcels,
            "total_parcels_with_shp" => $totalParcelsWithShp,
            "total_area_declared" => $totalAreaDeclared,
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $result
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
