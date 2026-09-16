<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private ?int $version = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxProjects = null;

    #[ORM\OneToMany(targetEntity: PositionAttribute::class, mappedBy: 'position', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $positionAttributes;

    #[ORM\OneToMany(targetEntity: PositionAccessRule::class, mappedBy: 'position', orphanRemoval: true, cascade: ['persist'])]
    private Collection $accessRules;

    #[ORM\ManyToMany(targetEntity: ProjectTag::class)]
    #[ORM\JoinTable(name: 'position_tag_link')]
    private Collection $projectTags;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->version = 1;
        $this->positionAttributes = new ArrayCollection();
        $this->accessRules = new ArrayCollection();
        $this->projectTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getMaxProjects(): ?int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(?int $maxProjects): static
    {
        $this->maxProjects = $maxProjects;
        return $this;
    }

    /**
     * @return Collection<int, PositionAttribute>
     */
    public function getPositionAttributes(): Collection
    {
        return $this->positionAttributes;
    }

    public function addPositionAttribute(PositionAttribute $pa): static
    {
        if (!$this->positionAttributes->contains($pa)) {
            $this->positionAttributes->add($pa);
            $pa->setPosition($this);
        }
        return $this;
    }

    public function removePositionAttribute(PositionAttribute $pa): static
    {
        $this->positionAttributes->removeElement($pa);
        return $this;
    }

    /**
     * @return Collection<int, PositionAccessRule>
     */
    public function getAccessRules(): Collection
    {
        return $this->accessRules;
    }

    public function addAccessRule(PositionAccessRule $rule): static
    {
        if (!$this->accessRules->contains($rule)) {
            $this->accessRules->add($rule);
            $rule->setPosition($this);
        }
        return $this;
    }

    public function removeAccessRule(PositionAccessRule $rule): static
    {
        $this->accessRules->removeElement($rule);
        return $this;
    }

    /**
     * @return Collection<int, ProjectTag>
     */
    public function getProjectTags(): Collection
    {
        return $this->projectTags;
    }

    public function addProjectTag(ProjectTag $tag): static
    {
        if (!$this->projectTags->contains($tag)) {
            $this->projectTags->add($tag);
        }
        return $this;
    }

    public function removeProjectTag(ProjectTag $tag): static
    {
        $this->projectTags->removeElement($tag);
        return $this;
    }
}
