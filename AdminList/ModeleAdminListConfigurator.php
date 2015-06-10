<?php

namespace Lle\PdfReportBundle\AdminList;

use Doctrine\ORM\EntityManager;

use Lle\PdfReportBundle\Form\ModeleAdminType;
use Lle\AdminListBundle\AdminList\FilterType\ORM;
use Lle\AdminListBundle\AdminList\Configurator\AbstractDoctrineORMAdminListConfigurator;
use Lle\AdminBundle\Helper\Security\Acl\AclHelper;

/**
 * The admin list configurator for Modele
 */
class ModeleAdminListConfigurator extends AbstractDoctrineORMAdminListConfigurator
{
    /**
     * @param EntityManager $em        The entity manager
     * @param AclHelper     $aclHelper The acl helper
     */
    public function __construct(EntityManager $em, AclHelper $aclHelper = null)
    {
        parent::__construct($em, $aclHelper);
        $this->setAdminType(new ModeleAdminType());
    }

    /**
     * Configure the visible columns
     */
    public function buildFields()
    {
        $this->addField('code', 'Code', true);
        $this->addField('filename', 'Filename', true);
    }


        /**
     * Configure the visible field in show
     */
    public function showFields()
    {
        $this->addShowField('code', 'Code');
        $this->addShowField('filename', 'Filename');
    }

    /**
     * Build filters for admin list
     */
    public function buildFilters()
    {
        $this->addFilter('code', new ORM\StringFilterType('code'), 'Code',array(), true);
        $this->addFilter('filename', new ORM\StringFilterType('filename'), 'Filename');
    }

    /**
     * Get bundle name
     *
     * @return string
     */
    public function getBundleName()
    {
        return 'LlePdfReportBundle';
    }

    /**
     * Get entity name
     *
     * @return string
     */
    public function getEntityName()
    {
        return 'Modele';
    }

    public function getUploadFileGetter() {
        return 'getFilePath';
    }

}
