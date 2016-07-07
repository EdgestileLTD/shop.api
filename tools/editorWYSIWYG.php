<?php
//error_reporting(E_ALL);

class BlogEditor {

    public function __construct($userFolder) {
        if (isset($_GET['filemanager'])) {
            chdir($_SERVER['DOCUMENT_ROOT']);
            $_SESSION['editor_images_access'] = "user_2";
            define('SE_INDEX_INCLUDED', true);
            include getcwd()."/system/main/init.php";
            $default_language = 'ru';
            $fm = $_GET['filemanager'];
            if (empty($fm))
                $fm = 'dialog';
            if ($fm=='upload') {
                $_SESSION["verify"] = "RESPONSIVEfilemanager";
                include getcwd().'/admin/filemanager/upload.php';
            } elseif ($fm=='getframe') {
                $lang = 'rus';
                $field_id = $_GET['field_id'];
                include getcwd().'/admin/views/image_editor.tpl';
            } elseif ($fm=='uploader') {
                $fma = @$_GET['filemanageraction'];
                if (file_exists(getcwd()."/admin/filemanager/uploader/{$fma}.php")) {
                    include getcwd()."/admin/filemanager/uploader/{$fma}.php";
                }
            } elseif (file_exists(getcwd()."/admin/filemanager/{$fm}.php")) {
                include getcwd()."/admin/filemanager/{$fm}.php";
            }
            exit;
        }
    }

    public function editorAccess() {
        return true;

    }

}

//  для filemanager from tinymce
if (isset($_GET['filemanager'])) {
    new BlogEditor('admin');
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />


    <script type="text/javascript" src="../../lib/js/jquery/jquery.min.js"></script >
    <script type="text/javascript" src="../../lib/js/tinymce/tinymce.min.js"></script >
    <script type="text/javascript" >
        tinymce.init({
            selector: "textarea.old",
            document_base_url: "/",
            //content_css: "/system/main/editor/tiny.css",
            safari_warning : false,
            remove_script_host : false,
            convert_urls : false,
            theme : "modern",
            forced_root_block : false,
            menubar : false,
            browser_spellcheck : true,
            language: "ru",
            convert_fonts_to_spans : true,
            toolbar: "undo redo pastetext | bold italic underline | alignleft aligncenter alignright alignjustify | "+
                " bullist numlist outdent indent | image link unlink media | table blockquote | removeformat fullscreen code",
            plugins: "link image paste code fullscreen media table",
            link_list: "<?php echo $_SERVER['PHP_SELF']; ?>?getpagelist",
            image_advtab: true,
            external_plugins: { "filemanager" : "/admin/filemanager/plugin.js" },
            external_filemanager_path: "<?php echo $_SERVER['PHP_SELF']; ?>"
        });
    </script >


    <script type="text/javascript">
        function utf8 (utftext) {
            var string = "";
            var i = 0;
            var c = c1 = c2 = 0;

            while ( i < utftext.length ) {

                c = utftext.charCodeAt(i);

                if (c < 128) {
                    string += String.fromCharCode(c);
                    i++;
                }
                else if((c > 191) && (c < 224)) {
                    c2 = utftext.charCodeAt(i+1);
                    string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                    i += 2;
                }
                else {
                    c2 = utftext.charCodeAt(i+1);
                    c3 = utftext.charCodeAt(i+2);
                    string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                    i += 3;
                }

            }
            return string;
        }

        function base64_decode( data ) {
            data = encodeURI(data);
            var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
            var o1, o2, o3, h1, h2, h3, h4, bits, i=0, enc='';

            do {  // unpack four hexets into three octets using index points in b64
                h1 = b64.indexOf(data.charAt(i++));
                h2 = b64.indexOf(data.charAt(i++));
                h3 = b64.indexOf(data.charAt(i++));
                h4 = b64.indexOf(data.charAt(i++));

                bits = h1<<18 | h2<<12 | h3<<6 | h4;

                o1 = bits>>16 & 0xff;
                o2 = bits>>8 & 0xff;
                o3 = bits & 0xff;

                if (h3 == 64)	  enc += String.fromCharCode(o1);
                else if (h4 == 64) enc += String.fromCharCode(o1, o2);
                else			   enc += String.fromCharCode(o1, o2, o3);
            } while (i < data.length);

            return utf8(enc);
        }

        function getRichText(){
            return tinymce.get('rich_text').getContent({format : 'html'});
        }
        function getPlainText(){
            var element = document.getElementById('plain_text');
            element.value = tinymce.get('rich_text').getContent({format : 'text'});
            return element.value;
        }
        function setFullScreen() {
            tinymce.activeEditor.execCommand('mceFullScreen');
        }
        function setContent(content) {
            content = base64_decode(content);
            tinymce.activeEditor.setContent(content);
            var element = document.getElementById('rich_text');
            element.value = tinymce.get('rich_text').getContent({format : 'text'});
        }
        $(window).load(function () { setFullScreen(); });
    </script>

</head>
<body>
    <textarea id="rich_text" class="old">  </textarea>
</body>
</html>


