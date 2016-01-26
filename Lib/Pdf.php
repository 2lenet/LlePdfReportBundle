<?php

namespace Lle\PdfReportBundle\Lib;

abstract class Pdf extends \TCPDF
{

    protected $debug = false;
    protected $item;
    protected $data;
    protected $container;

    public function __construct()
    {
        parent::__construct();
    }

    abstract public function generate();
    abstract public function myColors();
    abstract public function myFonts();

    protected function init()
    {
        return;
    }

    protected function log($str)
    {
        if ($this->debug == true) {
            echo $str.'<br/>';
        }
    }

    protected function colors($c){
        $colors = $this->myColors();
        if(is_array($colors) and isset($colors[$c])){
            return $this->hexaToArrayColor($colors[$c]);
        }
        if($c == 'default') return $this->hexaToArrayColor('000000');
        return $this->hexaToArrayColor(str_replace("#", "", $c));
    }

    public function setItem($item)
    {
        $this->item = $item;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function get($name)
    {
        return $this->container->get($name);
    }

    protected function getEntityManager(){
        return $this->get('doctrine.orm.entity_manager');
    }

    public function header()
    {
    }

    public function footer()
    {
        $h = $this->getPageHeight();
        $w = $this->getPageWidth();
        $this->rectangle($w, 9, 0, $h-9, 'default');
        $this->changeFont('default');
        $this->read($w-5, 6, 0, $h-9, $this->getPage(), 'R');
    }

    public function Output($name = 'doc.pdf', $dest = 'I')
    {
        parent::Output($name, $dest);
    }

    function nombreDePageSur($pdf)
    {

        if (false !== ( $file = file_get_contents($pdf) )) {
            $pages = preg_match_all("/\/Page\W/", $file, $matches);

            return $pages;

        }
    }

    public function month($index)
    {
        $mois = array('Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre');
        return $mois[$index - 1];
    }

    protected function hexaToArrayColor($color)
    {
        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));
        return array('R'=>$red,'G'=>$green,'B'=>$blue);
    }


    protected function drawImage($file, $x, $y, $width, $height, $options = array())
    {
        $round = (isset($options['round']))? $options['round']:false;
        $crop = (isset($options['crop']))? $options['crop']:false;
        $center = (isset($options['center']))? $options['center']:false;
        $palign = '';
        $align = 'N';
        $resize = 0;
        $dpi = 300;
        $scal = 1;
        $background = $this->hexaToArrayColor('FFFFFF');
        $target = $file;
        if ($file && @fopen($file, 'r')) {
            $size = @getimagesize($file);
            if ($size) {
                $nameFolder = basename(dirname($file));
                if ($crop == false) {
                    $size = $this->redimenssion($size[0], $size[1], $width, $height);
                    $w = $size[0];
                    $h = $size[1];
                    if ($center) {
                        $x +=  $width/2  - $w/2;
                        $y +=  $height/2 - $h/2;
                    }
                } else {
                    $w = $width;
                    $h = $height;
                    $target = __DIR__.'/../../../../web/media/pdf/'.md5($file);
                    if (true || !file_exists($target)) {
                        $size = getimagesize($file);
                        $i = new \Imagick($file);
                        $i->cropThumbnailImage($width*72/25.4, $height*72/25.4);
                        if ($round) {
                            $background = new \Imagick();
                            $background->newImage($size[0], $size[1], new \ImagickPixel('white'));
                            $i->roundCorners(500, 500);
                            $i->compositeImage($background, \imagick::COMPOSITE_DSTATOP, 0, 0);
                        }
                        $i->writeImage($target);
                    }
                    $file = $target;
                }
           //$this->rectangle($w,$h,$x,$y,'noir');
                $this->Image($file, $x, $y, $w, $h, '', '', $align, $resize, $dpi, $palign, false, false, 0, false, false, false, false, array());
            }
            return true;
        } else {
            $this->log('ERROR:'.$file.' NOT VALIDE');
            return false;
        }
    }

    public function drawCircle($x, $y, $r, $c)
    {
        $this->circle($x, $y, $r, 0, 360, 'F', array(), $this->colors($c), 2);
    }



    public function changeFontFamily($police)
    {
        $data = $this->get('kernel')->getRootDir().'/../data/fonts';
        $file = $data.$police.'.ttf';
        if (file_exists($file)) {
            $fontname = $this->addTTFfont($file, 'TrueTypeUnicode', '', 32, $data);
            $this->SetFont($fontname, '', null, $data.$fontname.'.php');
        } else {
            $this->SetFont($police);
        }
    }

    protected function read($x, $y, $html,$options = array())
    {
        $w = (isset($options['w']))? $options['w']:0;
        $h = (isset($options['h']))? $options['h']:0;
        $align = (isset($options['align']))? $options['align']:'C';
        $this->writeHTMLCell($w, $h, $x, $y, $html, 0, 0, false, true, $align, true);
    }

    protected function readInRect($w, $h, $x, $y, $html, $align, $c, $moveX = false)
    {
        $oldW = $w;
        $widthText = $this->getStringWidth($html);
        // $w = ($w > $widthText)? $w:$widthText+10;
        if ($w > $oldW && $moveX) {
            $x += ($oldW - $w)/2;
        }
        $this->rectangle($w, $h, $x, $y, $c);
        $this->read($x, $y, $html,array('w'=>$w,'h'=>$h,'align'=>$align));
    }

    protected function changeColor($c)
    {
        $this->SetTextColorArray($this->colors($c));
    }

    protected function changeFont($f)
    {
        $fonts = $this->myFonts();
        if(isset($fonts[$f])){
            $f = $fonts[$f];
        }else{
            $f = array('size'=>9,'color'=>'default','family'=>'helvetica');
        }
        if (isset($f['size'])) {
            $this->SetFontSize($f['size']);
        }
        if (isset($f['color'])) {
            $this->changeColor($f['color']);
        }
        if (isset($f['family'])) {
            $this->changeFontFamily($f['family']);
        }
        if (isset($f['style'])) {
            $this->changeFontStyle($f['style']);
        }
    }

    protected function changeFontStyle($style){
        $this->FontStyle = $style;
    }

    protected function rectangle($w, $h, $x, $y, $c)
    {
        $this->Rect($x, $y, $w, $h, 'F', array('width'=>0), $this->colors($c));
    }

    protected function rectangleEmpty($w, $h, $x, $y, $c)
    {
        $border_style = array('all' => array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 1, 'phase' => 0));
        $this->Rect($x, $y, $w, $h, 'D', $border_style, $this->colors($c));
    }

    protected function traceHLine($y,$options = array())
    {
        $w = $this->getPageWidth();
        $color = (isset($options['color']))? $options['color']:'default';
        $weight = (isset($options['weight']))? $options['weight']:0.5;
        $x = (isset($options['start']))? $options['start']:0;
        $w = ((isset($options['end']))? $options['end']:$w)-$x;
        $this->rectangle($w, $weight, $x, $y,$color);
    }

    protected function traceVligne($x,$options = array())
    {
        $h = $this->getPageHeight();
        $this->rectangle(0.5, $h, $x, 0, 'default');
    }

    protected function square($size, $x, $y, $c)
    {
        $this->rectangle($size, $size, $x, $y, $c);
    }


    protected function redimenssion($originalWidth, $originalHeight, $targetWidth, $targetHeight)
    {
        while ($originalWidth > $targetWidth || $originalHeight > $targetHeight) {
            $ratio = 1 / ($originalWidth / $targetWidth);
            if ($ratio >= 1) {
                $ratio = 0.99;
            }
            $originalWidth = $originalWidth * $ratio;
            $originalHeight = $originalHeight * $ratio;
        }
        return array($originalWidth,$originalHeight);
    }
}