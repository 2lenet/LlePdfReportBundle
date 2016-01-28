<?php
namespace Lle\PdfReportBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Lle\PdfReportBundle\Lib\Pdf;

class PdfGenerator
{

    protected $container;
    protected $class;
    protected $item;
    protected $data;
    protected $iterateDatas;

    public function __construct(ContainerInterface $container, $class)
    {
        $this->container = $container;
        $this->class = '\\'.$class;
    }

    public function setData($data){
        $this->data = $data;
    }

    public function addIterateData($data){
        $this->iterateDatas[] = $data;
    }

    public function get($name)
    {
        return $this->container->get($name);
    }

    public function getPdf(){
        $pdf = new $this->class;
        if ($pdf instanceof PDF) {
            if(count($this->iterateDatas)) return $this->iteratePdfs($pdf);
            $pdf->setData($this->data);
            $pdf->setContainer($this->container);
            $pdf->init();
            $pdf->generate();
        } else {
            throw new \Exception('PDF GENERATOR ERROR: '.$this->class.' n\'est pas une class PDF');
        }
        return $pdf;
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

    public function getPath(){
        $pdf = $this->getPdf();
        $tmp_file = tempnam($this->get('kernel')->getRootDir()."/pdfs/" , "pdf_".$this->item->getId().".pdf");
        $pdf->output($tmp_file, 'F');
        return $tmp_file;
    }


}
