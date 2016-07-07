<?php
    session_start();
?>

<!DOCTYPE html>
<html lang='ru'>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>API SiteEdit - тестирование</title>

    <link rel="shortcut icon" href="favicon.ico"; type="image/x-icon" />
    <link rel="icon" href="favicon.ico"; type="image/x-icon" />
    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="css/index.css" rel="stylesheet">
    <link href="js/fancybox/jquery.fancybox.css" rel="stylesheet">

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.js"></script>
    <script src="js/md5.js"></script>
    <script src="js/fancybox/jquery.fancybox.pack.js"></script>

    <script type="text/javascript">

        var projectToken, projectUrl;

        function auth() {
            var serial = $("#account_auth").val();
            var hash = $("#password_auth").val();
            if (!serial || !hash)
                return;

            hash = CryptoJS.MD5(hash) + "";
            $.ajax({
                url: "register.php",
                type: 'POST',
                dataType: "json",
                data: { serial: serial, hash: hash },
                beforeSend: function(){
                    $.fancybox.showLoading();
                },
                success: function(data) {
                    if (data && data.status == "ok") {
                        projectToken = data.data.token;
                        projectUrl = data.data.uri;
                    }
                },
                complete: function() {
                    $.fancybox.hideLoading();
                    if (projectToken) {
                        $(".api-data").show();
                        $("#name-project").show();
                        $("#form-auth").hide();
                        $("#name-project").html("Токен: " + projectToken + "<br>Адрес: " + projectUrl);
                    }
                }
            });
        }

        function execApi() {
            var apiObject = $("#api-object-name").val();
            var apiMethod = $("#api-object-method").val();
            var apiData = $("#api-object-data").val();

            $.ajax({
                url: "exec.php",
                type: 'POST',
                dataType: "json",
                data: { apiObject: apiObject, apiMethod: apiMethod, apiData: apiData },
                beforeSend: function(){
                    $.fancybox.showLoading();
                },
                success: function(data) {
                    $("#api-answer").text(data);
                    if (data && data.status == "ok") {
                        $("#api-answer").text(JSON.stringify(data.data));
                        $('#result-table').html(data.table);
                    }
                },
                complete: function() {
                    $.fancybox.hideLoading();
                }
            });

        }

        $(document).ready(function(){
            $("#btn-auth").click(function() { auth() });
            $("#send-to-api").click(function() { execApi() });
        });

    </script>


</head>

<body>
    <div class="container">
        <div class="row caption text-center">
            <H1>Тестирование API SiteEdit</H1>
        </div>
        <div class="row caption text-success">
           <?php
                if ($_SESSION['apiToken'])
                    echo '<H4>Токен: '  . $_SESSION['apiToken'] . '<br>Адрес: ' . $_SESSION['apiUrl'] .'</H4>';
                else echo '<H4 id="name-project"></H4>';
            ?>
        </div>
        <?php
            if (!$_SESSION['apiToken']) {
                echo '<div id = "form-auth" class="row col-lg-offset-3 col-lg-6" >';
                echo '<div class="input-group" >';
                echo '<span class="input-group-addon" ><span class="glyphicon glyphicon-user" ></span ></span >';
                echo '<input id = "account_auth" type = "text" class="form-control" placeholder = "Серийный номер" required />';
                echo '<span class="input-group-addon" ><span class="glyphicon glyphicon-lock" ></span ></span >';
                echo '<input id = "password_auth" type = "password" class="form-control" placeholder = "Серийный ключ" required />';
                echo '</div >';
                echo '<div class="input-group pull-right" >';
                echo '<button id = "btn-auth" class="btn btn-primary" type = "button" > Авторизоваться</button >';
                echo '</div >';
                echo '</div >';
          }
        ?>

        <div class="api-data  <?php if (!$_SESSION['apiToken']) echo "block-hidden"; ?> ">

            <div class="clearfix"></div>

            <div class="row">
                <legend>Исходные данные:</legend>
                <form>
                    <div class="input-group" >
                        <input id="api-object-name" type="text" value="Contacts" placeholder="API объект" />
                        <input id="api-object-method" type="text" placeholder="API метод" value="Fetch.api" />
                    </div>
                    <div class="input-group">
                        <label for="api-object-data">Данные для запроса в формате JSON :</label>
                    </div>
                    <div class="input-group" style="width: 100%">
                        <textarea id="api-object-data" rows="5">{"limit": 5, "sortBy": "firstName"}</textarea>
                    </div>
                    <button id="send-to-api" class="btn btn-primary" type="button" >Отправить</button >
                </form>
            </div>
            <br>
            <div class="row">
                <legend>Ответ API:</legend>
                <div id="result-table">

                </div>
                <div class="input-group" style="width: 100%">
                    <textarea id="api-answer" rows="20"></textarea>
                </div>
            </div>
        </div>

    </div>

</body>
</html>



