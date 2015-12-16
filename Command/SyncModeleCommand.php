<?php

namespace Lle\PdfReportBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Sensio\Bundle\GeneratorBundle\Command\AutoComplete\EntitiesAutoCompleter;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Lle\AdminListBundle\Generator\AdminListGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Lle\PdfReportBundle\Entity\Modele;

/**
 * Generates a LleAdminList
 */
class SyncModeleCommand extends ContainerAwareCommand
{


    /**
     * @see Command
     */
    protected function configure()
    {
        $this->setName('lle:pdfreport:sync')->setDescription('Syncronise la bdd et le dossier data/report');
    }

    /**
     * Executes the command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @throws \RuntimeException
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getKernel()->getContainer();
        $em =  $this->getContainer()->get('doctrine')->getManager();
        $fileManager = $container->get('lle_file_manager');
        $fileManager->setIgnore(array('..','.','.gitkeep','.directory'));
        $helper = $this->getHelper('question');
        foreach($fileManager->ls('data/report') as $file){
            $question = new Question('Code pour "'.$file['name'].'" (vide pour ignoré): ');
            $code = $helper->ask($input, $output, $question);
            if($code){
                $modele = new Modele();
                $modele->setCode($code);
                $modele->setFilename($file['name']);
                $em->persist($modele);
            }
        }
        $em->flush();
        echo 'Modèle enregistré';
        echo "\n";
    }
}
