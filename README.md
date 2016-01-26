# LlePdfReportBundle
Bundle to convert IReport specification files ( jaspersoft ) to pdf file in pure PHP
Gestionaire de rapport

Editeur à utiliser pour créer les rapports : iReport ( validé version 4.7.0 )



README OBSOLETE ( version sf1.4 )

Puis dans l'action effectuer l'opération suivante :

 function executeListConfCmd($request) {
        $filename = sfConfig::get('sf_root_dir').'/data/reports/conf_cmd.jrxml';
        $cmd = $this->getRoute()->getObject();
        $rep = new PdfReport(file_get_contents($filename));
        $rep->generate($cmd, $cmd->getCommandeLignes());

        $rep->Output('cmd.pdf', 'I');
        return sfView::NONE;
    }


Les deux paramétres sont l'objet pour l'entete et la doctrine collection pour les lignes de détail.

Tous les éléments ireport ne sont pas géré pour le moment.

----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
PDF normal (joris):
Cree le service:
lle_alef.pdf_agenda:
    class:        Lle\PdfReportBundle\Service\PdfGenerator
    arguments:    ['@service_container',Lle\AlefBundle\Utils\Pdf\Agenda]

Cree votre class (ici Lle\AlefBundle\Utils\Pdf\Agenda):

<?php
class Agenda extends Lle\PdfReportBundle\Lib\Pdf{
    protected $debug = false; private $user;
    public function myColors(){return array('blanc' => 'FFFFFF','default'=> '000000');}
    public function myFonts(){return array('titre' => array('size'=>12,'color'=>'noir','family'=>'courier','style'=>'BU'));}
    public function init(){
        setlocale(LC_ALL, 'fr_FR'); $this->setAutoPageBreak(false, 0); $this->setMargins(0,0,0); $this->AddPage('L');
        $this->user = $this->data['user'];
    }
    public function generate(){
        $this->changeFont('titre');
        $this->w(10,10,'Hello '.$this->user->getName());
        $this->traceHline(20);
        $this->drawImage('web/img/logo.png',0,0);
    }
    //public function footer(){}
    //public function header(){}
}

Dans votre controleur:
$pdf = $this->get('lle_alef.pdf_agenda');
$pdf->setData(array('user'=>$user));
$pdf->show();

Pour crée plusieur page a partire d'un PDF (par exemple liste de contrat) vous devez juste ajouter les data avec addIterableData, les data ajouter avec setData sont toujours disponible mais sont les meme pour tous les PDF:

Dans votre controleur:
$pdf = $this->get('lle_alef.pdf_agenda');
$pdf->setData(array('user'=>$user));
foreach($contrats as $contrat) $pdfAgenda->addIterateData(array('contrat'=>$contrat));
$pdfAgenda->show();

----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
PDF report (joris):
$this->get('lle_pdf_report')->getResponse('code_modele',$objet,$iterable);
