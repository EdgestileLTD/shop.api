<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel=stylesheet href="codemirror/lib/codemirror.css">
    <link rel=stylesheet href="codemirror/lib/docs.css">

    <style>
        .CodeMirror { height: 100%; border: 1px solid #ddd; font-size: 14px; }
        .CodeMirror pre { padding-left: 7px; line-height: 1.25; }
    </style>

</head>

<body style="margin: 0; overflow: hidden; height: 100%" scrolling="no" scroll="no">
    <textarea id=context></textarea>

    <script src="codemirror/lib/codemirror.js"></script>
    <script src="codemirror/mode/xml/xml.js"></script>
    <script src="codemirror/mode/javascript/javascript.js"></script>
    <script src="codemirror/mode/css/css.js"></script>
    <script src="codemirror/mode/htmlmixed/htmlmixed.js"></script>
    <script src="codemirror/addon/edit/matchbrackets.js"></script>
    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("context"), {
            lineNumbers: true,
            mode: "text/html",
            matchBrackets: true
        });
    </script>
</body>
</html>
