<?php

namespace App\Entity;

use App\Repository\LegalDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=App\Repository\ConsentDecreeRepository::class)
 */
class ConsentDecree extends LegalDocument
{

}
