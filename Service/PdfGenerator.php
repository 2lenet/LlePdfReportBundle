<?php
namespace Lle\PdfReportBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Lle\PdfReportBundle\Lib\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfGenerator
{

    protected $container;
    protected $class;
    protected $data;
    protected $iterateDatas;
    protected $pdf = null;

    public function __construct(ContainerInterface $container, $class)
    {
        $this->container = $container;
        $this->class = '\\'.$class;
    }

    public function setData($data){
        $this->data = $data;
        return $this;
    }

    public function addIterateData($data){
        $this->iterateDatas[] = $data;
    }

    public function get($name)
    {
        return $this->container->get($name);
    }

    public function getPdf(){
        if(!$this->pdf){
            $pdf = new $this->class;
            if ($pdf instanceof PDF) {
                if(count($this->iterateDatas)){
                    $pdf = $this->iteratePdfs($pdf);
                }else{
                    $pdf->setData($this->data);
                    $pdf->setContainer($this->container);
                    $pdf->initiate();
                    $pdf->generate();
                }
                $pdf->setTitle($pdf->title());
                $this->pdf = $pdf;
            } else {
                throw new \Exception('PDF GENERATOR ERROR: '.$this->class.' n\'est pas une class PDF');
            }
        }
        return $this->pdf;
    }

    private function iteratePdfs($pdf){
        $pdf->setContainer($this->container);
        foreach($this->iterateDatas as $data){
            $pdf->setData(array_merge($data,$this->data));
            $pdf->init();
            $pdf->generate();
        }
        return $pdf;
    }


    public function show()
    {
        $pdf = $this->getPdf();
        return $pdf->Output('Pdf.pdf', 'I');
    }

    public function getResponse($filename = null){
        $response =  new BinaryFileResponse($this->getPath($filename));
        $response->headers->set('Content-Type', 'application/pdf');
        return $response;
    }


    public function getPath($filename = null){
        $pdf = $this->getPdf();
        if($filename) $this->pdf->setTitle($filename);
        $filename = ($filename)? $filename:"pdf_".md5(microtime()).".pdf";
        $tmp_file = tempnam($this->get('kernel')->getRootDir()."/pdfs/" , $filename);
        $pdf->output($tmp_file, 'F');
        return $tmp_file;
    }



}
