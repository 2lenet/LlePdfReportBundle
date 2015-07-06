<?php

namespace Lle\PdfReportBundle\Lib;

class PdfReport extends \TCPDF {

    public function __construct($xml_report_string) {

        $this->load($xml_report_string);

        if ((intval($this->rdata['pageWidth'])) > (intval($this->rdata['pageHeight'])))
            $orientation = 'L';
        else
            $orientation = 'P';
        $unit = 'pt'; //$rdata['report']['unit'];
        $format = array(intval($this->rdata['pageWidth']), intval($this->rdata['pageHeight']));
        //$format = 'A4';
        $unicode = true;
        $encoding = 'utf-8';
        $this->decX = 0;
        parent::__construct($orientation, $unit, $format, $unicode, $encoding);
        $this->SetFont('helvetica');
        $this->SetFont('helvetica', 'B', 10);
        //$this->setMargins(intval($this->rdata['leftMargin']),intval($this->rdata['topMargin']),intval($this->rdata['rightMargin']),1);
        $this->setMargins(intval($this->rdata['leftMargin']), 0, intval($this->rdata['rightMargin']), 1);
        $this->bottomMargin = intval($this->rdata['bottomMargin']);
        $this->setAutoPageBreak(0);
        $this->setViewerPreference('PrintScaling', 'None');
        $this->vars = array();
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
        $rdata = new \SimpleXMLElement($xml_report_string);
        $this->rdata = $rdata;
    }

    public function initPage($data, $datacoll) {
        $this->dataObj = $data;
        $this->data = $data;
        $this->dataColl = $datacoll;
        $this->AddPage();
        $this->SetXY(0, 0);
    }

    public function generate($data, $datacoll) {
        $this->initPage($data, $datacoll);
        $this->generateGroup('title', $this->rdata->title);
        $this->generateGroup('pageHeader', $this->rdata->pageHeader);
        $current_group = 0;
        $i = 1;
        $count = $this->dataColl->count();
        $current_group = array();
        $previousDataObj = Null;


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
                            if ($startPage)
                                $this->newPage();
                        }

                        $this->generateGroupItem('groupHeader', $group->groupHeader);
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
            $this->generateGroupItem('detail', null, $maxY);
            $i++;
            $previousDataObj = $dataObj;
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

    public function newPage() {
        //print "newPage";
        $obj = $this->dataObj;
        $this->dataObj = $this->data;
        //$this->setXY(0,$this->getPageHeight() - $this->rdata->pageFooter->band['height']- $this->bottomMargin);
        $this->generateGroup('columnFooter', $this->rdata->columnFooter);
        $this->generateGroup('pageFooter', $this->rdata->pageFooter);
        $this->AddPage();
        $this->SetXY(0, 0);
        $this->generateGroup('pageHeader', $this->rdata->pageHeader);
        $this->dataObj = $obj;
    }

    public function generateGroupItem($key, $group = null, $maxY = 0) {
        //print "key=$key";
        if (!$group)
            $group = $this->rdata->$key;
        if ($group->band) {
            // version qui marche tout le temps mais laisse beaucoup de blanc.
            $ruptY = $this->getPageHeight() - $this->bottomMargin - $this->rdata->pageFooter->band['height'] - $this->rdata->summary->band['height'] - $group->band['height'];
            // version optimiste.
            //$ruptY = $this->getPageHeight() - $this->bottomMargin - $this->rdata->pageFooter->band['height']- $group->band['height'];
            // gere les sauts de page si la bande est en stretch
            if ($key == 'detail' && $group->band['splitType'] == 'Stretch') {
                // on triple la hauteur
                $ruptY = $ruptY - 3 * $group->band['height'];
            }
            if ($this->getY() > $ruptY && $key == "detail") { //columnFooter" && $key !="pageFooter" && $key !="summary") {
                //$this->maxY = $this->getY();
                $this->newPage();
            }
            if ($key == 'summary') {
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
        $method = "generate" . ucfirst($key);
        $this->$method($item);
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
        $field = preg_filter('/{([a-zA-Z_.]*)}/', "$1", $exp);
        $vars = preg_filter('/#([a-zA-Z_.]*)#/', "$1", $exp);
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

            if (substr($field, 0, 4) == 'date') {
                $data = "";
                try {
                    if ($obj->get($field)) {
                        try {
                            $data = $obj->getDateTimeObject($field)->format('d/m/Y');
                        } catch (Exception $e) {
                            $data = $obj->get($field);
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
                $data = call_user_func(array($obj, $method));
            } else {
                $data = (string) $obj . '->' . $method; //. '-' . $e;
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
                    '/({[a-zA-Z_.]*})/', function ($matches) {
                return $this->getFieldData((string) $matches[0], $this->dataObj, $this->item['pattern']);
            }, $item->textFieldExpression
            );
        }

        $this->setReportXY($item);
        $this->setFontStyle($item);
        $align = $this->getAlign($item);
        $y = $this->getY();
        $nb = $this->MultiCell($item->reportElement['width'] + 2, $item->reportElement['height'] + 2, $data, 0, $align, 0, 0);
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
        }
        $this->setFont('helvetica', $style, $size);
    }

    public function getAlign($item) {
        if ($item->textElement) {
            if ($item->textElement['textAlignment'] == 'Center')
                return 'C';
            if ($item->textElement['textAlignment'] == 'Right')
                return 'R';
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

        $image_name = basename(str_replace('\\\\', "/", str_replace('"', '', $item->imageExpression)));

        $image = 'images/' . $image_name;

        if (is_file($image)) {
            $this->Image($image, $x, $y, $item->reportElement['width']);
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
        //die('--->'.$bcolor);
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

        $this->setReportXY($item);
        $align = $this->getAlign($item);

        $this->item = $item;

        $namespaces = $item->getNameSpaces(true);
        $hc = $item->children($namespaces['hc']);
        $html = $hc->html;

        $html->htmlContentExpression = $this->getFieldData($html->htmlContentExpression, $this->dataObj, $this->item['pattern']);
        
        // Remplacement de variable dans le texte
        $text = preg_replace_callback(
                '/({[a-zA-Z_.]*})/', function ($matches) {
            return $this->getFieldData((string) $matches[0], $this->dataObj, '');
        }, $html->htmlContentExpression
        );        

        // Transformation markdown => HTML
        $pattern = array();
        $pattern[] = '/\# (.*)/';
        $pattern[] = '/\## (.*)/';
        $pattern[] = '/\### (.*)/';
        $pattern[] = '/\*\*(.*)\*\*/';
        $pattern[] = '/_(.*)_/';

        $replacement = array();
        $replacement[] = '<h1>${1}</h1>';
        $replacement[] = '<h2>${1}</h2>';
        $replacement[] = '<h3>${1}</h3>';
        $replacement[] = '<b>${1}</b>';
        $replacement[] = '<i>${1}</i>';

        $text = preg_replace($pattern, $replacement, $text);

        $this->writeHTMLCell($item->reportElement['width'] + 5, $item->reportElement['height'] + 2, '', '', (string) $text, 0, $align);
    }

}
