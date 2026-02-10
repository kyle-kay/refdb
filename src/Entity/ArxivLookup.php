<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class ArxivLookup
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    protected string $arxivId;

    /**
     * @ORM\Column(type="string", length=1000)
     */
    protected string $reference;

    /**
     * @ORM\Column(type="string", length=500, nullable=true)
     */
    protected ?string $title = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    protected ?array $authors = null;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    protected ?string $category = null;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    protected ?string $year = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArxivId(): string
    {
        return $this->arxivId;
    }

    public function setArxivId(string $arxivId): void
    {
        $this->arxivId = $arxivId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getAuthors(): ?array
    {
        return $this->authors;
    }

    public function setAuthors(?array $authors): void
    {
        $this->authors = $authors;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function setYear(?string $year): void
    {
        $this->year = $year;
    }
}
