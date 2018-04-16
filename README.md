vos modeles : data/report

----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
PDF normal (joris):


Cree le service:
```yaml
lle_alef.pdf_agenda:
    class:        Lle\PdfReportBundle\Service\PdfGenerator
    arguments:    ['@service_container',Lle\AlefBundle\Utils\Pdf\Agenda]
```

Cree votre class (ici Lle\AlefBundle\Utils\Pdf\Agenda):

```php
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
```
Dans votre controleur:

```php
<?php
$pdf = $this->get('lle_alef.pdf_agenda');
$pdf->setData(array('user'=>$user));
$pdf->show();
```

Pour crée plusieur page a partire d'un PDF (par exemple liste de contrat) vous devez juste ajouter les data avec addIterableData, les data ajouter avec setData sont toujours disponible mais sont les meme pour tous les PDF:

Dans votre controleur:
```php 
<?php
$pdf = $this->get('lle_alef.pdf_agenda');
$pdf->setData(array('user'=>$user));
foreach($contrats as $contrat) $pdfAgenda->addIterateData(array('contrat'=>$contrat));
$pdfAgenda->show();
```

----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------
----------------------------------------------------------------------------------------------------------------------------

PDF report (joris):

Ajouter les modeles a la bdd (Ne fonctionne qu'avec le chemain data/report):
```
php app/console lle:pdfreport:sync
```

appeler le modele

```php 
<?php
$this->get('lle_pdf_report')->getResponse('code_modele',$objet,$iterable); //return reponse BinaryFileResponse
```

il existe les methodes suivante:
```php
<?php
getPdfFile($code, $obj, $iterable = null,$filepath = null) // returne un fichier sous forme de filepath
getEmptyPdf() // return un PDF vide
getPdf($code,$obj,$iterable = null,$pdf = null) // return un TCPDF

//Itération: (uniquement avec la sorti TCPDF)
$pdf = $service->getEmptyPdf();
foreach($coll as $elm) $pdf = $service->getPdf('code',$elm,$elm->getColl(),$pdf);
$pdf->output();
```
