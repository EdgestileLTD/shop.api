<?php
    session_start();

    function getTableJson($data) {
        $items = $data["items"];
        $result = '<table class="table table-bordered table-striped">';
        $i = 0;
        foreach ($items as $item) {
            if (!$i) {
                $result .= "<tr>";
                foreach ($item as $key=>$value)
                    $result .= "<th>$key</th>";
                $result .= "</tr>";
            }
            $result .= "<tr>";
            foreach ($item as $key=>$value) {
                $value = print_r($value, 1);
                $result .= "<td>$value</td>";
            }
            $result .= "</tr>";
            $i++;
        }
        $result .= '</table>';
        return $result;
    }


    $apiToken = $_SESSION['apiToken'];
    $apiUrl = $_SESSION['apiUrl'];

    $apiMethod = $_POST['apiMethod'];
    $apiObject = $_POST['apiObject'];
    $apiData = $_POST['apiData'];

    $isTest = true;
    $apiFolder = "1";
    if ($isTest)
        $apiFolder = "development";

    $url = "$apiUrl/api/$apiFolder/$apiObject/$apiMethod";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Token: $apiToken"));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $apiData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch));

    if ($apiMethod == "Fetch.api")
        $result->table = getTableJson((array) $result->data);

    echo json_encode($result);