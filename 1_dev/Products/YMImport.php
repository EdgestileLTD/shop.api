<?php


define('PATH_CACHE', API_ROOT . "../ym_cache/");
define('PROJECT_HOST', PATH_ROOT . $json->hostname . '/public_html/');

if (!file_exists(PATH_CACHE))
    mkdir(PATH_CACHE);

class parserHtml implements IteratorAggregate
{
    protected $_source = '';
    /**
     * @var DOMDocument
     */
    protected $_dom = null;
    /**
     * @var DOMXpath
     * */
    protected $_xpath = null;

    public function __construct($htmlString = '')
    {
        $this->loadHtml($htmlString);
    }

    public static function fromHtml($htmlString)
    {
        $me = new self();
        $me->loadHtml($htmlString);
        return $me;
    }

    public static function fromDom($dom)
    {
        $me = new self();
        $me->loadDom($dom);
        return $me;
    }

    public function loadDom($dom)
    {
        $this->_dom = $dom;
        $this->_xpath = new DOMXpath($this->_dom);
    }

    public function loadHtml($htmlString = '')
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        if (strlen($htmlString)) {
            libxml_use_internal_errors(TRUE);
            $dom->loadHTML($htmlString);
            libxml_clear_errors();
        }
        $this->loadDom($dom);
    }

    function __invoke($expression)
    {
        return $this->get($expression);
    }

    public function get($expression)
    {
        if (strpos($expression, ' ') !== false) {
            $a = explode(' ', $expression);
            foreach ($a as $k => $sub) {
                $a[$k] = $this->getXpathSubquery($sub);
            }
            return $this->getElements(implode('', $a));
        }
        return $this->getElements($this->getXpathSubquery($expression));
    }

    protected function getXpathSubquery($expression)
    {
        $query = '';
        if (preg_match("/(?P<tag>[a-z0-9]+)?(\[(?P<attr>\S+)=(?P<value>\S+)\])?(#(?P<id>\S+))?(\.(?P<class>\S+))?/ims", $expression, $subs)) {
            $tag = $subs['tag'];
            $id = isset($subs['id']) ? $subs['id'] : null;
            $attr = isset($subs['attr']) ? $subs['attr'] : null;;
            $attrValue = isset($subs['value']) ? $subs['value'] : null;
            $class = isset($subs['class']) ? $subs['class'] : null;
            if (!strlen($tag))
                $tag = '*';
            $query = '//' . $tag;
            if (strlen($id)) {
                $query .= "[@id='" . $id . "']";
            }
            if (strlen($attr)) {
                $query .= "[@" . $attr . "='" . $attrValue . "']";
            }
            if (strlen($class)) {
                //$query .= "[@class='".$class."']";
                $query .= '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
            }
        }
        return $query;
    }

    protected function getElements($xpathQuery)
    {
        $newDom = new DOMDocument('1.0', 'UTF-8');
        $root = $newDom->createElement('root');
        $newDom->appendChild($root);
        if (strlen($xpathQuery)) {
            $nodeList = $this->_xpath->query($xpathQuery);
            if ($nodeList === false) {
                throw new Exception('Malformed xpath');
            }
            foreach ($nodeList as $domElement) {
                $domNode = $newDom->importNode($domElement, true);
                $root->appendChild($domNode);
            }
            return self::fromDom($newDom);
        }
    }

    public function toXml()
    {
        return $this->_dom->saveXML();
    }

    public function toHtml()
    {
        return $this->_dom->saveHtml();
    }

    public function toArray($xnode = null)
    {
        $array = array();
        if ($xnode === null) {
            $node = $this->_dom;
        } else {
            $node = $xnode;
        }
        if ($node->nodeType == XML_TEXT_NODE) {
            return $node->nodeValue;
        }
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $array[$attr->nodeName] = $attr->nodeValue;
            }
        }
        if ($node->hasChildNodes()) {
            if ($node->childNodes->length == 1) {
                $array[$node->firstChild->nodeName] = $this->toArray($node->firstChild);
            } else {
                foreach ($node->childNodes as $childNode) {
                    if ($childNode->nodeType != XML_TEXT_NODE) {
                        $array[$childNode->nodeName][] = $this->toArray($childNode);
                    }
                }
            }
        }
        if ($xnode === null) {
            return reset(reset($array)); // first child
        }
        return $array;
    }

    public function getIterator()
    {
        $a = $this->toArray();
        return new ArrayIterator($a);
    }
}

class Parser
{
    public function getContent($url, $isCache = true)
    {
        $agents = file_get_contents(__DIR__ . "/agents.txt");
        $agents = explode("\r\n", $agents);
        $agent = $agents[rand(0, count($agents) - 1)];
        //$proxy = '89.250.207.195';

        $file_cache = PATH_CACHE . md5($url);
        if (file_exists($file_cache) && $isCache)
            return file_get_contents($file_cache);

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, $agent);
        //curl_setopt($curl_handle, CURLOPT_PROXY, $proxy);
        $result = curl_exec($curl_handle);
        curl_close($curl_handle);

        if (strlen($result) > 8192 && $isCache) {
            $file = fopen($file_cache, "w+");
            fputs($file, $result);
            fclose($file);
        }

        return $result;
    }

    public function loadImages($images) {
        foreach ($images as $image) {
            $url = $image->imageUrl;
            $lang = FOLDER_SHOP ? FOLDER_SHOP : 'rus';
            $path = PROJECT_HOST . "images/{$lang}/shopprice/" . $image->imageFile;
            if (!file_exists($path))
                file_put_contents($path, $this->getContent($url, false));
        }
    }

    public function runYandexMarketCard($url, $html)
    {

        $product = new stdClass();
        $path = parse_url($url);
        $path = $path["path"];
        $product->article = array_pop(explode("/", $path));

        $images = array();

        $saw = new parserHtml($html);
        $res = $saw->get('.product-card')->toArray();
        foreach ($res["meta"] as $meta) {
            if ($meta["itemprop"] == "brand")
                $product->brandName = $meta["content"];
            if ($meta["itemprop"] == "name")
                $product->name = $meta["content"];
            if ($meta["itemprop"] == "category")
                $product->category = $meta["content"];
        }
        if (!empty($product->brandName))
            $product->name = $product->brandName . " " . $product->name;

        // картинки
        $res = $saw->get('.product-card-gallery__thumbs-item')->toArray();
        $i = 1;
        if (!empty($res["a"]))
            $res = array($res);
        foreach ($res as $item) {
            $image = new stdClass();
            $image->imageAlt = $product->name;
            $image->sortIndex = $i++;
            $image->imageUrl = "https:" . $item["a"]["href"];
            $image->imageFile = array_pop(explode("=", $image->imageUrl));
            $image->isMain = $i == 1;
            $images[] = $image;
        }
        $product->images = $images;

        // цена
        $res = $saw->get('.product-card__price-value')->toArray();
        $product->price = preg_replace('/[^0-9]/', '', $res["#text"]);

        //кол-во
        $product->isInfinitely = true;

        //характеристики
        $specifications = array();
        $res = $saw->get('.product-spec-wrap__body')->toArray();
        if (!empty($res["h2"]))
            $res = array($res);
        $tag = '<dl id="product-spec-" class="product-spec"><dt class="product-spec__name"><span class="product-spec__name-inner">';
        $pattern = '|' . $tag . '[^<]*|';
        preg_match_all($pattern, $html, $items);
        $specNames = $items[0];
        foreach ($res as $itemGroup) {
            $nameGroup = !empty($itemGroup["h2"]) && !empty($itemGroup["h2"][0]) ? $itemGroup["h2"][0]["#text"] : "";
            if (!empty($itemGroup["dl"])) {
                foreach ($itemGroup["dl"] as $item) {
                    $spec = new stdClass();
                    $spec->nameGroup = $nameGroup;
                    $spec->name = $item["dt"]["0"]["span"]["#text"];
                    $n = count($specifications);
                    if (empty($spec->name) && !empty($specNames[$n]))
                        $spec->name = str_replace($tag, "", $specNames[$n]);
                    $spec->value = $item["dd"]["0"]["span"]["#text"];
                    $specifications[] = $spec;
                }
            }
        }

        $product->specifications = $specifications;

        return $product;
    }
}

if (!empty($json->html)) {
    $parser = new Parser;
    $json = $parser->runYandexMarketCard($json->urlParse, $json->html);
    $status = array();
    if (!empty($json->name)) {
        $parser->loadImages($json->images);
        $IS_OUTPUT_DATA = false;
        require_once __DIR__ . "/Save.php";
    }
    if ($status['status'] == "ok") {
        $IS_OUTPUT_DATA = true;
        $json = new stdClass();
        $json->ids[] = $status["data"]["id"];
        require_once __DIR__ . "/Info.php";
    }
}