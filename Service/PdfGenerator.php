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

    public function __construct(ContainerInterface $container, $class)
    {
        $this->container = $container;
        $this->class = '\\'.$class;
    }

    public function setItem($item)
    {
        $this->item = $item;
    }

    public function setData($data){
        $this->data = $data;
    }

    public function get($name)
    {
        return $this->container->get($name);
    }

    public function getPdf(){
        $pdf = new $this->class;
        if ($pdf instanceof PDF) {
            $pdf->setItem($this->item);
            $pdf->setData($this->data);
            $pdf->setContainer($this->container);
            $pdf->init();
            $pdf->generate();
        } else {
            throw new \Exception('PDF GENERATOR ERROR: '.$this->class.' n\'est pas une class PDF');
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
