<?php 

namespace Lle\PdfReportBundle\Service;

use Lle\PdfReportBundle\Entity\Modele;
use Lle\PdfReportBundle\Lib\PdfReport as Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfReport{

    private $container;


    public function __construct($container){
        $this->container = $container;
    }

    private function get($name){
        return $this->container->get($name);
    }

    public function getReportDir(){
        return $this->getRootDir().'data/report/';
    }

    public function getUploadDir(){
        return $this->getRootDir().'web/uploads/modele/';
    }

    public function getRootDir(){
        return $this->get('kernel')->getRootDir().'/../';
    }

    public function getModele($code){
        $em = $this->container->get('doctrine.orm.entity_manager');
        $modele =  $em->getRepository('LlePdfReportBundle:Modele')->findOneByCode($code);
        if(!$modele){
            throw new \Exception('Le code '.$code.' ne correspond a aucun modele');
        }
        return $modele;
    }

    public function getResponse($code, $obj, $iterable = null){
        return new BinaryFileResponse($this->getPdfFile($code, $obj, $iterable));
    }


    public function getPdfFile($code, $obj, $iterable = null,$filepath = null) {
        
        $pdf = $this->getPdf($code, $obj, $iterable);
        if(!$filepath){
            $filepath = tempnam('pdf_report' , "pdf_".$code.microtime()."_");
        }
        $pdf->output($filepath, 'F');
        return $filepath;
    }

    public function getEmptyPdf(){
        $pdf = new Pdf();
        $pdf->setRootPath($this->getRootDir());
        return $pdf;
    }

    public function getPdf($code,$obj,$iterable = null,$pdf = null){
        $modele = $this->getModele($code);
        $files = explode(',',$modele->getFilename());
        if($pdf == null) $pdf = $this->getEmptyPdf();
        foreach($files as $filename){
            $pdf->load(file_get_contents($this->getFilePath($filename)));
            $pdf->generate($obj, $iterable);
        }
        return $pdf;
    }

    public function getFilePath($filename){
        $paths = array($this->getReportDir().$filename,$this->getUploadDir().$filename);
        foreach($paths as $path){
            if(file_exists($path)) return $path;
        }
        throw new \Exception('Modele '.$filename.' not found in '.implode(',',$paths));
    }
}
