<?php

namespace App\Entity;

use App\Repository\LegalDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
class LegalDocument
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="App\LegalDocumentPersistentIdGenerator")
     * @ORM\Column(type="string")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $case_number;

    /**
     * @ORM\Column(type="integer")
     */
    private $version;

    /**
     * @ORM\Column(type="string", length=512)
     */
    private $case_name;

    /**
     * @ORM\Column(type="json")
     */
    private $document;

    public function getCaseName(): ?string
    {
        return $this->case_name;
    }

    public function setCaseName(string $case_name): self
    {
        $this->case_name = $case_name;

        return $this;
    }

    public function getCaseNumber(): ?string
    {
        return $this->case_number;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getDocument(): ?array
    {
        return $this->document;
    }

    // public function setDocument(array $document): self
    public function setDocument($document): self
    {
        $this->document = [$document];

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setCaseNumber(string $case_number): self
    {
        $this->case_number = $case_number;

        return $this;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }
}
