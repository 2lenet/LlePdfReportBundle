<?php

namespace Lle\PdfReportBundle\Lib;

use Lle\PdfReportBundle\Lib\Parsedown\Parsedown;

class PdfReport extends \TCPDF {

    private $fake = false;
    private $rootPath = null;

    public function __construct($xml_report_string = null, $fake = false) {
        parent::__construct();
        $this->fake = $fake;

        if($xml_report_string){
            $this->load($xml_report_string);
        }

        $this->SetFont('helvetica', 'B', 10);
        $this->setViewerPreference('PrintScaling', 'None');
        $this->vars = array();
        $this->setAutoPageBreak(0);
    }

    public function setRootPath($rootPath){
        $this->rootPath = $rootPath;
    }

    public function setFake($fake){
        $this->fake = $fake;
    }

    public function setVars($vars) {
        $this->vars = $vars;
    }

    public function setViewerPreference($preference, $value) {
        $this->viewer_preferences[$preference] = $value;
    }

    static function getFilename($model, $lang, $taxgroup = null) {
        $filename = null;
        if ($taxgroup) {
            $filename = sfConfig::get('sf_root_dir') . '/data/reports/' . $model . '_' . $lang . '_' . $taxgroup . '.jrxml';
        }
        if (!$filename || !file_exists($filename)) {
            $filename = sfConfig::get('sf_root_dir') . '/data/reports/' . $model . '_' . $lang . '.jrxml';
        }
        if (!file_exists($filename)) {
            $filename = sfConfig::get('sf_root_dir') . '/data/reports/' . $model . '.jrxml';
        }
        return $filename;
    }

    public function load($xml_report_string) {
        $this->rdata  = new \SimpleXMLElement($xml_report_string);
        $this->setPageUnit('pt');
        $this->decX = 0;
        $this->setPageUnit('pt');
        $this->setMargins(intval($this->rdata['leftMargin']), 0, intval($this->rdata['rightMargin']), 1);
        $this->bottomMargin = intval($this->rdata['bottomMargin']);
    }

    public function initPage($data, $datacoll) {
        $this->dataObj = $data;
        $this->data = $data;
        $this->dataColl = $datacoll;
        $this->AddPage(); //c ici que ça bug
        $this->SetXY(0, 0);
    }

    public function generate($data, $datacoll) {
        $this->initPage($data, $datacoll);
        if ((intval($this->rdata['pageWidth'])) > (intval($this->rdata['pageHeight'])))
            $orientation = 'L';
        else
            $orientation = 'P';
        $format = array(intval($this->rdata['pageWidth']), intval($this->rdata['pageHeight']));
        $this->setPageFormat($format,$orientation);
        $this->generateGroup('title', $this->rdata->title);
        $this->generateGroup('pageHeader', $this->rdata->pageHeader);
        $this->generateGroup('columnHeader', $this->rdata->columnHeader);
        $current_group = 0;
        $i = 1;
        $count = count($this->dataColl);
        $current_group = array();
        $previousDataObj = Null;
        $current_group_header = null;

        if($datacoll != null) {
            foreach ($datacoll as $dataObj) {
                $this->dataObj = $dataObj;
                $k = 0;

                foreach ($this->rdata->group as $group) {
                    $datag = $this->getFieldData($group->groupExpression, $dataObj);

                    $current_group[$k] = (isset($current_group[$k])) ? $current_group[$k] : null;
                    if ($datag) {

                        if ($current_group[$k] !== $datag) {  // changement de groupe

                            $attrs = $group->attributes();
                            $startPage = (string) $attrs['isStartNewPage'];

                            if ($current_group[$k]) {
                                $this->dataObj = $previousDataObj;
                                $this->generateGroupItem('groupFooter', $group->groupFooter);
                                $this->dataObj = $dataObj;
                                if ($startPage){
                                    $this->newPage();
                                }
                            }

                            $this->generateGroupItem('groupHeader', $group->groupHeader);
                            $current_group_header['group'] = $group->groupHeader;
                            $current_group_header['dataObj'] = $dataObj;
                            $current_group[$k] = $datag;
                            for ($i = $k + 1; $i < count($current_group); $i++)
                                $current_group[$i] = null;
                        }
                    }
                    $k++;
                }

                if ($i == $count && $this->rdata->detail->band['splitType'] == 'Stretch') {
                    $maxY = $this->getPageHeight() - $this->bottomMargin - $this->rdata->summary->band['height'] - $this->rdata->columnFooter->band['height'] - $this->rdata->pageFooter->band['height'] - $this->rdata->lastPageFooter->band['height'] - 1;
                } else {
                    $maxY = 0;
                }
                $this->generateGroupItem('detail', null, $maxY, $current_group_header);
                $i++;
                $previousDataObj = $dataObj;
            }
        }
        if (count($this->rdata->group)) {
            $this->generateGroupItem('groupFooter', $this->rdata->group->groupFooter);
        }
        $this->dataObj = $this->data;
        $this->generateGroup('columnFooter', $this->rdata->columnFooter);
        //$this->setXY(0,$this->getPageHeight() - $this->rdata->pageFooter->band['height'] - $this->rdata->summary->band['height']- $this->bottomMargin);
        if ($this->rdata->lastPageFooter) {
            $this->generateGroup('lastPageFooter', $this->rdata->lastPageFooter);
        } else {
            $this->generateGroup('pageFooter', $this->rdata->pageFooter);
        }
        $this->generateGroup('summary', $this->rdata->summary);
    }

    public function generateGroup($key, $group = null) {
        if (!$group)
            $group = $this->rdata->$key;
        $this->generateGroupItem($key, $group);
    }

    public function newPage($current_group_header = null) {
        //print "newPage";
        $obj = $this->dataObj;
        $this->dataObj = $this->data;
        //$this->setXY(0,$this->getPageHeight() - $this->rdata->pageFooter->band['height']- $this->bottomMargin);
        $this->generateGroup('columnFooter', $this->rdata->columnFooter);
        $this->generateGroup('pageFooter', $this->rdata->pageFooter);
        $this->AddPage();
        $this->SetXY(0, 0);
        $this->generateGroup('pageHeader', $this->rdata->pageHeader);
        if($current_group_header != null) {
            $this->dataObj = $current_group_header['dataObj'];
            $this->generateGroupItem('groupHeader', $current_group_header['group']);
        }
        $this->dataObj = $obj;
    }

    public function generateGroupItem($key, $group = null, $maxY = 0, $current_group_header = null) {
        //print "generateGroupItem key=$key<br />";
        if (!$group)
            $group = $this->rdata->$key;
        if ($group->band) {
            // version qui marche tout le temps mais laisse beaucoup de blanc.
            if ($key != 'columnHeader') {
                $this->ruptY = $this->getPageHeight() - $this->bottomMargin - $this->rdata->pageFooter->band['height'] - $this->rdata->summary->band['height'] - $group->band['height'];
            } else {
                $this->ruptY = $this->getPageHeight() - $this->bottomMargin - $this->rdata->pageFooter->band['height'] - $this->rdata->summary->band['height'];
            }
            // version optimiste.
            //$ruptY = $this->getPageHeight() - $this->bottomMargin - $this->rdata->pageFooter->band['height']- $group->band['height'];
            // gere les sauts de page si la bande est en stretch
            if ($key == 'detail' && $group->band['splitType'] == 'Stretch') {
                // on triple la hauteur
                $this->ruptY = $this->ruptY - 3 * $group->band['height'];
            }
            //print "key=".$key."-Y:" . $this->getY()."<br />";
            if ($this->getY() > $this->ruptY && $key == "detail") { //columnFooter" && $key !="pageFooter" && $key !="summary") {
                //$this->maxY = $this->getY();
                $this->newPage($current_group_header);
            }
            if ($key == 'summary' || $key=='lastPageFooter') {
                // alignement en bas pour le groupe summary
                $this->group_y = $this->getPageHeight() - $this->bottomMargin - $group->band['height'];
            } else {
                $this->group_y = $this->getY();
            }
            $this->maxY = $maxY;
            foreach ($group->band->children() as $node) {
                $ikey = $node->getName();
                $this->generateItem($ikey, $node);
                if (($group->band['splitType'] == 'Stretch') && ($this->getY() > $this->maxY))
                    $this->maxY = $this->getY();
            }
            if ($group->band['splitType'] == 'Stretch') {
                $this->setY($this->maxY);
                if ($this->getY() < ($this->group_y + $group->band['height']))
                    $this->setY($this->group_y + $group->band['height']);
            } else {
                $this->setY($this->group_y + $group->band['height']);
            }
            $this->group_y = $this->getY();
        }
    }

    public function generateItem($key, $item) {
        $noprint = (isset($item->reportElement['key']) and $item->reportElement['key'] == 'noprint');
        $method = "generate" . ucfirst($key);
        if($noprint) $this->startLayer('no-print',false);
        $this->$method($item);
        if($noprint) $this->endLayer();
    }

    public function getMaxYItem($key, $item) {
        $method = "getMaxY" . ucfirst($key);
        $this->$method($item);
    }

    public function setReportXY($item) {
        $this->setXY($item->reportElement['x'] + $this->decX + $this->lMargin, $this->group_y + $item->reportElement['y'] + $this->tMargin);
        if ($item->reportElement['forecolor']) {
            $rgb = $this->hex2rgb($item->reportElement['forecolor']);
            $this->setTextColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            $this->setTextColor(0);
        }
    }

    public function getMaxYSubreport($item) {
        // pas gèré
        return 0;
    }

    public function generateSubreport($item) {
        $this->setXY($item->reportElement['x'] + $this->lMargin, $this->group_y + $item->reportElement['y']);
        $this->decX = $item->reportElement['x'];
        $report = $item->subreportExpression;
        $key = $item->reportElement['key'];
        $filename = sfConfig::get('sf_root_dir') . '/data/reports/' . $report;
        if (file_exists($filename)) {

            // save context
            list($old_rdata, $old_datacoll, $old_dataobj) = array($this->rdata, $this->dataColl, $this->dataObj);

            // switch context
            $subrdata = new \SimpleXMLElement(file_get_contents($filename));
            $this->rdata = $subrdata;
            if (substr($key[0], 0, 1) == '#') {
                $this->dataColl = $this->vars[substr($key, 1)];
            } else {
                $this->dataColl = @$this->dataObj->get($key);
            }

            if (count($this->dataColl)) {

                $this->generateGroup('title', $this->rdata->title);
                //$this->setXY($item->reportElement['x'] + $this->lMargin, $this->group_y - $this->rdata->title->band['height']);
                foreach ($this->dataColl as $d) {
                    $this->dataObj = $d;
                    $this->generateGroup('detail', $this->rdata->detail);
                }
            }
            // resotore context
            list($this->rdata, $this->dataColl, $this->dataObj) = array($old_rdata, $old_datacoll, $old_dataobj);
        }
        $this->decX = 0;
    }

    public function generateStaticText($item) {

        $this->setReportXY($item);
        $this->setFontStyle($item);
        $align = $this->getAlign($item);

        $this->item = $item;
        $item->text = preg_replace_callback(
                '/({[a-zA-Z_.]*})/', function ($matches) {
            return $this->getFieldData((string) $matches[0], $this->dataObj, $this->item['pattern']);
        }, $item->text
        );

        $this->MultiCell($item->reportElement['width'] + 5, $item->reportElement['height'] + 2, (string) $item->text, 0, $align);
    }

    public function generatePrintWhenExpression($item) {

    }

    public function getFieldData($exp, $obj, $pattern = '') {
        if (!$obj)
            return "";
        $field = preg_filter('/{([a-zA-Z_.]*)}(\$([0-9a-zA-Z_.-]*)\$)?/', "$1", $exp);
        $vars = preg_filter('/#([a-zA-Z0-9_.]*)#/', "$1", $exp);
        $args = preg_filter('/{([a-zA-Z_.]*)}(\$([0-9a-zA-Z_.-]*)\$)?/', "$3", $exp);
        // print "field:" . $field."<br />";
        // print $vars."<br />";
        // print "args:" . $args."<br />";
        if ($vars) {
            return @$this->vars[$vars];
        }
        if ($field) {
            $fieldArr = explode('.', $field, 2);
            //print $field."<br />";
            // champs de relation ( Client.nom )
            if (count($fieldArr) > 1) {
                #if ($obj->relatedExists($this->to_camel_case($fieldArr[0]))) {
                $method = 'get' . $this->to_camel_case($fieldArr[0]);
                $data_obj = call_user_func(array($obj, $method));
                try {
                    return $this->getFieldData("{" . $fieldArr[1] . "}", $data_obj);
                } catch (Exception $e) {
                    return "";
                }
                #} else {
                #    return "";
                #}
            }

            if ($field == 'now') {
                $date = new \DateTime();
                return $date->format('d/m/Y');
            }

            if (substr($field, 0, 4) == 'date') {

                $data = "";
                $method = 'get' . $this->to_camel_case($fieldArr[0]);
                $date_obj = call_user_func(array($obj, $method));

                try {
                    if ($date_obj) {
                        try {
                            $data = $date_obj->format('d/m/Y');
                        } catch (Exception $e) {
                            $data = $date_obj;
                        }
                    } else {
                        $data = "";
                    }
                    return $data;
                } catch (Exception $e) {
                    print "exception, $e";
                    $data = "";
                }
                return $data;
            }
            $method = 'get' . $this->to_camel_case($field);
            if (method_exists($obj, $method)) {
                $data = call_user_func(array($obj, $method), $args);
            } else {
                if(is_object($obj)){
                    $data = get_class($obj) . '->' . $method; //. '-' . $e;
                }else{
                    $data = $obj;
                }
            }
            if ($pattern == '€' || $pattern == '€2') {
                return @number_format($data, 2, ',', ' ');
            } elseif ($pattern == '€3') {
                return @number_format($data, 3, ',', ' ');
            } else {
                return $data;
            }
        } else {
            return $exp;
        }
    }

    public function generateBreak($item) {

    }

    public function generateTextField($item) {
        if ($item->textFieldExpression == '$V{PAGE_NUMBER}') {
            $data = $this->PageNo();
        } else if ($item->textFieldExpression == '$V{PAGE_TOTAL}') {
            $data = $this->getAliasNbPages();
        } else if ($item->textFieldExpression == '$V{PAGE_OF}') {
            $data = $this->PageNo() . ' / ' . $this->getAliasNbPages();
        } else if ($item->textFieldExpression == '$V{CURRENT_DATE}') {
            $data = date('d/m/Y', time());
        } else {
            $this->item = $item;
            $data = preg_replace_callback(
                '/({[a-zA-Z0-9_.]*}(\$([0-9a-zA-Z_.-]*)\$)?)/',
                function ($matches) {
                    $elm = $this->getFieldData((string) $matches[0], $this->dataObj, $this->item['pattern']);
                    if($elm instanceof \DateTime){
                        return $elm->format('d/m/Y');
                    }else{
                        return $elm;
                    }
                },
                $item->textFieldExpression
            );
        }

        $this->setReportXY($item);
        $this->setFontStyle($item);
        $align = $this->getAlign($item);
        $y = $this->getY();
        
        $isHtml = false;
        if($data != strip_tags($data)) {
            $isHtml = true;
        }

        $nb = $this->MultiCell($item->reportElement['width'] + 2, $item->reportElement['height'] + 2, $data, 0, $align, 0, 0, '', '', true, 0, $isHtml);
        
        if (trim($data)) {
            $height = $this->getStringHeight($item->reportElement['width'] + 2, $data);
        } else {
            $height = $item->reportElement['height'];
        }
        $this->setY($item->reportElement['y'] + $this->group_y + $height);
    }

    public function setFontStyle($item) {
        $size = 10;
        $style = '';
        $fontName = 'helvetica';
        if ($item->textElement) {
            if ($item->textElement->font['size']) {
                $size = $item->textElement->font['size'];
            }
            //print_r($item->textElement->font);
            if ($item->textElement->font['isBold'] == 'true') {
                $style .='B';
            }
            if ($item->textElement->font['isUnderline'] == 'true') {
                $style .='U';
            }
            if ($item->textElement->font['isItalic'] == 'true') {
                $style .='I';
            }
            if($item->textElement->font['pdfFontName']) {
                $fontName = $item->textElement->font['pdfFontName'];
            }
        }
        $this->setFont($fontName, $style, $size);
    }

    public function getAlign($item) {
        if ($item->textElement) {
            if ($item->textElement['textAlignment'] == 'Center')
                return 'C';
            if ($item->textElement['textAlignment'] == 'Right')
                return 'R';
            if ($item->textElement['textAlignment'] == 'Justify')
                return 'J';
        }
        return 'L';
    }

    public function generateImage($item) {
        $x = $item->reportElement['x'] + $this->decX + $this->lMargin;
        if ($item->reportElement['ignoreMargin']) {
            $y = $this->group_y + $item->reportElement['y'] + $this->tMargin + $this->bottomMargin;
        } else {
            $y = $this->group_y + $item->reportElement['y'] + $this->tMargin;
        }

        $this->item = $item;
        $data = preg_replace_callback(
            '/({[a-zA-Z_.]*})/',
            function ($matches) {
                $elm = $this->getFieldData((string) $matches[0], $this->dataObj, '');
                return $elm;
            },
            $item->imageExpression
        );
        if(count(explode('/', $data)) > 2){
            $image_name = $data;
        }else{
            $image_name = basename(str_replace('\\', "/", str_replace('"', '', $data)));
        }
        if($this->rootPath){
            //ici on pense à gagner du temps dans le future
            $where = array('data/report/images','web/images','data/images','data/report/images','data/report','web');
            $image = null;
            foreach($where as $w){
                if (is_file($this->rootPath.$w.'/'.$image_name)) {
                    $image = $this->rootPath.$w.'/'.$image_name;
                    break;
                }
            }
            if($image){
                $this->Image($image, $x, $y, $item->reportElement['width']);
            }else{
                //throw new \Exception($image_name.' not found in '.implode(',',$where));
            }
        }else{
            //ici on n'y a pas pensé ... good luck
            $image = 'images/' . $image_name;
            if (is_file($image)) {
                $this->Image($image, $x, $y, $item->reportElement['width']);
            }
        }
    }

    public function generateGenericElement($item) {
        if ($item->genericElementType["name"] == "qrcode") {
            $forecolor = $item->reportElement['forecolor'];
            if ($forecolor) {
                $fgcolor = array(hexdec(substr($forecolor, 1, 2)), hexdec(substr($forecolor, 3, 2)), hexdec(substr($forecolor, 5, 2)));
            } else {
                $fgcolor = array(0, 0, 0);
            }
            $x = $item->reportElement['x'] + $this->decX + $this->lMargin;
            $y = $this->group_y + $item->reportElement['y'];
            $style = array(
                'border' => false,
                'padding' => 'auto',
                'fgcolor' => $fgcolor,
                'bgcolor' => false
            );
            $url = $this->getFieldData((string) $item->reportElement["key"], $this->dataObj);
            $this->write2DBarcode($url, 'QRCODE,H', $x, $y, $item->reportElement['width'], $item->reportElement['height'], $style, 'N');
        } else if ($item->genericElementType["name"] == "barcode") {
            $forecolor = $item->reportElement['forecolor'];
            if ($forecolor) {
                $fgcolor = array(hexdec(substr($forecolor, 1, 2)), hexdec(substr($forecolor, 3, 2)), hexdec(substr($forecolor, 5, 2)));
            } else {
                $fgcolor = array(0, 0, 0);
            }
            $x = $item->reportElement['x'] + $this->decX + $this->lMargin;
            $y = $this->group_y + $item->reportElement['y'];
            $style = array(
                'border' => false,
                'padding' => 'none',
                'position' => 'C',
                'fgcolor' => $fgcolor,
                'bgcolor' => false
            );
            $code = $this->getFieldData((string) $item->reportElement["key"], $this->dataObj);
            $this->write1DBarcode($code, 'EAN13', $x, $y, '', 30, 1, $style, 'N');
        }
    }

    public function generateRectangle($item) {
        $y = $this->group_y + $item->reportElement['y'] + $this->tMargin;
        $style = 'S';
        if ($item->reportElement['mode'] == 'Opaque') {
            if (isset($item->graphicElement->pen['lineWidth']) and (int) $item->graphicElement->pen['lineWidth'] < 1) {
                $style = 'F';
            } else {
                $style = 'FD';
            }
        }
        $this->setLineAttrib($item);
        $this->Rect(
                $item->reportElement['x'] + $this->decX + $this->lMargin, $y, $item->reportElement['width'], $item->reportElement['height'], $style, array(), $this->hex2rgb($item->reportElement['backcolor']));
    }

    public function setLineAttrib($item) {

        if ($item->graphicElement->pen['lineWidth']) {
            $this->setLineWidth($item->graphicElement->pen['lineWidth']);
        } else {
            $this->setLineWidth(1);
        }
        if ($item->graphicElement->pen['lineStyle'] == 'Dotted') {
            $this->SetLineStyle(array('cap' => 'butt', 'join' => 'miter', 'dash' => 2));
        } else {
            $this->SetLineStyle(array('cap' => 'square', 'join' => 'miter', 'dash' => 0));
        }
    }

    public function generateLine($item) {
        $bcolor = (string) $item->reportElement['forecolor'];
        $y = $this->group_y + $item->reportElement['y'] + $this->tMargin;
        $x = $item->reportElement['x'] + $this->decX + $this->lMargin;
        $width = $item->reportElement['width'];
        if ($item->reportElement['stretchType'] == "RelativeToBandHeight") {
            $height = $this->maxY - $y;
            if ($height < $item->reportElement['height'])
                $height = $item->reportElement['height'];
        } else {
            $height = $item->reportElement['height'];
        }
        // correction car iReport met automatiquement 1 ( compte la bordure )
        if ($width == 1)
            $width = 0;
        if ($height == 1)
            $height = 0;
        $this->setLineAttrib($item);
        $style = array('color' => $this->hexaToArrayColor($bcolor));
        $this->Line($x, $y, $x + $width, $y + $height, $style);
        $this->setLineStyle(array('color' => $this->hexaToArrayColor('#000000')));
        $this->setY($y);
    }

    public function hexaToArrayColor($color) {
        $red = hexdec(substr($color, 1, 2));
        $green = hexdec(substr($color, 3, 2));
        $blue = hexdec(substr($color, 5, 2));
        return array('R' => $red, 'G' => $green, 'B' => $blue);
    }

    public function Header() {

    }

    public function Footer() {

    }

    /**
     * Send the document to a given destination: string, local file or browser. In the last case, the plug-in may be used (if present) or a download ("Save as" dialog box) may be forced.<br />
     * The method first calls Close() if necessary to terminate the document.
     * @param string $name The name of the file. If not given, the document will be sent to the browser (destination I) with the name doc.pdf.
     * @param string $dest Destination where to send the document. It can take one of the following values:<ul><li>I: send the file inline to the browser. The plug-in is used if available. The name given by name is used when one selects the "Save as" option on the link generating the PDF.</li><li>D: send to the browser and force a file download with the name given by name.</li><li>F: save to a local file with the name given by name.</li><li>S: return the document as a string. name is ignored.</li></ul>If the parameter is not specified but a name is given, destination is F. If no parameter is specified at all, destination is I.<br />Note: for compatibility with previous versions, a boolean value is also accepted (false for F and true for D).
     * @since 1.0
     * @see Close()
     *
     * Redéfini ici car modification des header pour IE
     */
    public function Output($name = '', $dest = '') {
        //Output PDF to some destination
        //Finish document if necessary
        if ($this->state < 3) {
            $this->Close();
        }
        //Normalize parameters
        if (is_bool($dest)) {
            $dest = $dest ? 'D' : 'F';
        }
        $dest = strtoupper($dest);
        if ($dest == '') {
            if ($name == '') {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }
        switch ($dest) {
            case 'I': {
                    //Send to standard output
                    if (ob_get_contents()) {
                        $this->Error('Some data has already been output, can\'t send PDF file');
                    }
                    if (php_sapi_name() != 'cli') {
                        //We send to a browser
                        header('Content-Type: application/pdf');
                        if (headers_sent()) {
                            $this->Error('Some data has already been output to browser, can\'t send PDF file');
                        }
                        header('Content-Length: ' . strlen($this->buffer));
                        header('Content-disposition: inline; filename="' . $name . '"');
                    }
                    echo $this->buffer;
                    break;
                }
            case 'D': {
                    //Download file
                    if (ob_get_contents()) {
                        $this->Error('Some data has already been output, can\'t send PDF file');
                    }
                    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
                        header('Content-Type: application/force-download');
                    } else {
                        header('Content-Type: application/pdf');
                    }
                    if (headers_sent()) {
                        $this->Error('Some data has already been output to browser, can\'t send PDF file');
                    }
                    header('Content-Length: ' . strlen($this->buffer));
                    header("Cache-Control: max_age=0");
                    header('Content-disposition: attachment; filename="' . $name . '"');
                    header("Pragma: public");
                    echo $this->buffer;
                    break;
                }
            case 'F': {
                    //Save to local file
                    $f = fopen($name, 'wb');
                    if (!$f) {
                        $this->Error('Unable to create output file: ' . $name);
                    }
                    fwrite($f, $this->buffer, strlen($this->buffer));
                    fclose($f);
                    break;
                }
            case 'S': {
                    //Return as a string
                    return $this->buffer;
                }
            default: {
                    $this->Error('Incorrect output destination: ' . $dest);
                }
        }
        return '';
    }

    function IncludeJS($script) {
        $this->javascript = $script;
    }

    function from_camel_case($str) {
        $str[0] = strtolower($str[0]);
        $func = create_function('$c', 'return "_" . strtolower($c[1]);');
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    /**
     *  * Translates a string with underscores
     *   * into camel case (e.g. first_name -> firstName)
     *    *
     *     * @param string $str String in underscore format
     *      * @param bool $capitalise_first_char If true, capitalise the first char in $str
     *       * @return string $str translated into camel caps
     *        */
    function to_camel_case($str, $capitalise_first_char = false) {
        if ($capitalise_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        $func = create_function('$c', 'return strtoupper($c[1]);');
        return preg_replace_callback('/_([a-z])/', $func, $str);
    }

    function _putjavascript() {
        $this->_newobj();
        $this->n_js = $this->n;
        $this->_out('<<');
        $this->_out('/Names [(EmbeddedJS) ' . ($this->n + 1) . ' 0 R]');
        $this->_out('>>');
        $this->_out('endobj');
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/S /JavaScript');
        $this->_out('/JS ' . $this->_textstring($this->javascript));
        $this->_out('>>');
        $this->_out('endobj');
    }

    function _putresources() {
        parent::_putresources();
        if (!empty($this->javascript)) {
            $this->_putjavascript();
        }
    }

    function _putcatalog() {
        $oid = parent::_putcatalog();
        if (!empty($this->javascript)) {
            $this->_out('/Names <</JavaScript ' . ($this->n_js) . ' 0 R>>');
        }
        return $oid;
    }

    function AutoPrint($dialog = false) {
        //Lance la boîte d'impression ou imprime immediatement sur l'imprimante par défaut
        $param = ($dialog ? 'true' : 'false');
        $script = "print($param);";
        $this->IncludeJS($script);
    }

    function hex2rgb($hex) {
        $hex = str_replace("#", "", $hex);

        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $rgb = array($r, $g, $b);
        //return implode(",", $rgb); // returns the rgb values separated by commas
        return $rgb; // returns an array with the rgb values
    }

    public function generateComponentElement($item) {

        $this->SetFont('helvetica');
        $this->SetFont('helvetica', '', 10);
        $align = $this->getAlign($item);

        $this->setReportXY($item);

        $this->item = $item;

        $namespaces = $item->getNameSpaces(true);
        $hc = $item->children($namespaces['hc']);
        $html = $hc->html;
        $css = '<style>' . $item->reportElement['style'] . '</style>';

        $html->htmlContentExpression = $this->getFieldData($html->htmlContentExpression, $this->dataObj, $this->item['pattern']);

        // Remplacement de variable dans le texte
        $text = preg_replace_callback(

                '/({[a-zA-Z0-9_.]*})/', function ($matches) {
                    if($this->fake) {
                        return (string) $matches[0];
                    }
                    return $this->getFieldData((string) $matches[0], $this->dataObj, '');
                },
                $html->htmlContentExpression
        );

        /*$text = preg_replace_callback(
                '/({[a-zA-Z_.]*})((_format_({[a-zA-Z_.]*}))?)/', function ($matches) {
                    if($this->fake) {
                        return (string) $matches[0];
                    }
                    return $this->getFieldData((string) $matches[0], $this->dataObj, '');
                },
                $html->htmlContentExpression
        ); */

        // Transformation markdown => HTML
        $parsedown = new Parsedown();
        $parsedown->setBreaksEnabled(true);

        $html = $parsedown->text($text);
        //print $html;
        //die();

        $tagvs = array(
            'h1' => array(0 => array('h' => 1, 'n' => 3), 1 => array('h' => 1, 'n' => 3)),
            'h2' => array(0 => array('h' => 2, 'n' => 3), 1 => array('h' => 2, 'n' => 3))
        );
        $this->setHtmlVSpace($tagvs);

        //$chapitres = preg_split("/(?<!<\/h[1-3]>\n)(?=<h[1-3]>)/m", $html, -1,  PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
        $chapitres = preg_split("/(?<!<\/h[1-3]>\n)(?=<h[1-3]>|<p>)/m", $html, -1,  PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        foreach ($chapitres as $key => $chapitre) {

            $chapitre = preg_replace('/(<h[2|3]>)/', '<br />\1', $chapitre);
            $height = $this->evaluateHeight('html', $chapitre);
            if ($key == count($chapitres)-1) {
                $this->ruptY -=  $this->rdata->lastPageFooter->band['height'];
            }

            if ($this->getY() + $height > $this->ruptY) {
                $this->setXY(0,$this->getPageHeight() - $this->rdata->pageFooter->band['height']- $this->bottomMargin);
                $this->newPage();
                $this->SetFont('helvetica');
                $this->SetFont('helvetica', '', 10);
            }
            $this->writeHTMLCell($item->reportElement['width'] + 5, '', '', $this->getY() + 5, $css.$chapitre, 0, 1, false, true, $align, true);
        }
    }

    public function evaluateHeight($mode, $text = '') {

        if ((intval($this->rdata['pageWidth'])) > (intval($this->rdata['pageHeight'])))
            $orientation = 'L';
        else
            $orientation = 'P';
        $unit = 'pt'; //$rdata['report']['unit'];
        $format = array(intval($this->rdata['pageWidth']), intval($this->rdata['pageHeight']));
        //$format = 'A4';
        $unicode = true;
        $encoding = 'utf-8';

        $pdf = new \TCPDF($orientation, $unit, $format, $unicode, $encoding);
        $pdf->AddPage();

        $pdf->startTransaction();


        $start_y = $pdf->GetY();
        //print "start:" . $start_y . "<br />";
        $start_page = $pdf->getPage();

        if ($mode == 'html') {
            $pdf->writeHTMLCell(0, 0, $pdf->GetX(), $start_y, (string) $text, 0, 1);
        } else {
            die('PdfReport::evaluateHeight $mode='.$mode.'. Mode prie en compt : html');
        }

        $end_y = $pdf->GetY();
        //print "end:" . $end_y . "<br />";
        $end_page = $pdf->getPage();

        $height = 0;
        if ($end_page == $start_page) {
            $height = $end_y - $start_y;
        } else {
            for ($page = $start_page; $page <= $end_page; ++$page) {
                $pdf->setPage($page);
                if ($page == $start_page) {

                    $height = $pdf->h - $start_y - $pdf->bMargin;
                } elseif ($page == $end_page) {
                    $height = $end_y - $pdf->tMargin;
                } else {
                    $height = $pdf->h - $pdf->tMargin - $pdf->bMargin;
                }
            }
        }
        $pdf->rollbackTransaction();
        unset($pdf);

        return $height;
    }

}
