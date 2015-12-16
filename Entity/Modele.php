<?php

namespace Lle\PdfReportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Modele
 *
 * @ORM\Table(name="modele",indexes={@ORM\Index(name="code", columns={"code"})}))
 * @ORM\Entity(repositoryClass="Lle\PdfReportBundle\Entity\ModeleRepository")
 *
 */
class Modele
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=30)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string")
     */
    private $filename;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return Modele
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set filename
     *
     * @param string $filename
     * @return Modele
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string 
     */
    public function getFilename()
    {
        return $this->filename;
    }

}
