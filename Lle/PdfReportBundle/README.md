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
