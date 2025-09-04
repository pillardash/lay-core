<?php

namespace BrickLayer\Lay\Libs\JsonLd;

/**
 * JSON-LD Generator Class
 * 
 * A comprehensive class for generating structured data markup
 * with full autocomplete support and type safety.
 * 
 * @author Augment Agent
 * @version 1.0.0
 */
class AIJsonLd
{
    private array $data;
    
    /**
     * Create a new JSON-LD generator instance
     */
    public function __construct()
    {
        $this->data = [
            "@context" => "https://schema.org"
        ];
    }
    
    /**
     * Create a new instance (static factory method)
     */
    public static function create(): self
    {
        return new self();
    }
    
    /**
     * Set the schema type (e.g., 'Hospital', 'Organization', 'LocalBusiness')
     */
    public function setType(string $type): self
    {
        $this->data["@type"] = $type;
        return $this;
    }
    
    /**
     * Set the name of the entity
     */
    public function setName(string $name): self
    {
        $this->data["name"] = $name;
        return $this;
    }
    
    /**
     * Set an alternate name
     */
    public function setAlternateName(string $alternateName): self
    {
        $this->data["alternateName"] = $alternateName;
        return $this;
    }
    
    /**
     * Set the description
     */
    public function setDescription(string $description): self
    {
        $this->data["description"] = $description;
        return $this;
    }
    
    /**
     * Set the URL
     */
    public function setUrl(string $url): self
    {
        $this->data["url"] = $url;
        return $this;
    }
    
    /**
     * Set the logo URL
     */
    public function setLogo(string $logoUrl): self
    {
        $this->data["logo"] = $logoUrl;
        return $this;
    }
    
    /**
     * Set images (can be string or array)
     */
    public function setImages($images): self
    {
        $this->data["image"] = is_array($images) ? $images : [$images];
        return $this;
    }
    
    /**
     * Set postal address
     */
    public function setAddress(
        string $streetAddress,
        string $addressLocality,
        string $addressRegion,
        string $postalCode,
        string $addressCountry
    ): self {
        $this->data["address"] = [
            "@type" => "PostalAddress",
            "streetAddress" => $streetAddress,
            "addressLocality" => $addressLocality,
            "addressRegion" => $addressRegion,
            "postalCode" => $postalCode,
            "addressCountry" => $addressCountry
        ];
        return $this;
    }
    
    /**
     * Set geo coordinates
     */
    public function setGeoCoordinates(string $latitude, string $longitude): self
    {
        $this->data["geo"] = [
            "@type" => "GeoCoordinates",
            "latitude" => $latitude,
            "longitude" => $longitude
        ];
        return $this;
    }
    
    /**
     * Set contact information
     */
    public function setContact(
        string $telephone,
        string $email,
        string $contactType = "customer service",
        array $availableLanguages = ["English"]
    ): self {
        $this->data["telephone"] = $telephone;
        $this->data["email"] = $email;
        
        if (!isset($this->data["contactPoint"])) {
            $this->data["contactPoint"] = [];
        }
        
        $this->data["contactPoint"][] = [
            "@type" => "ContactPoint",
            "telephone" => $telephone,
            "contactType" => $contactType,
            "availableLanguage" => $availableLanguages
        ];
        
        return $this;
    }
    
    /**
     * Add additional contact point
     */
    public function addContactPoint(
        string $telephone,
        string $contactType,
        array $availableLanguages = ["English"]
    ): self {
        if (!isset($this->data["contactPoint"])) {
            $this->data["contactPoint"] = [];
        }
        
        $this->data["contactPoint"][] = [
            "@type" => "ContactPoint",
            "telephone" => $telephone,
            "contactType" => $contactType,
            "availableLanguage" => $availableLanguages
        ];
        
        return $this;
    }
    
    /**
     * Set operating hours (24/7 format: "Mo-Su 00:00-23:59")
     */
    public function setOperatingHours(
        string $openingHours,
        array $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
        string $opens = "00:00",
        string $closes = "23:59"
    ): self {
        $this->data["openingHours"] = $openingHours;
        $this->data["openingHoursSpecification"] = [
            "@type" => "OpeningHoursSpecification",
            "dayOfWeek" => $daysOfWeek,
            "opens" => $opens,
            "closes" => $closes
        ];
        return $this;
    }
    
    /**
     * Set founder information
     */
    public function setFounder(string $name, string $jobTitle = null): self
    {
        $founder = [
            "@type" => "Person",
            "name" => $name
        ];
        
        if ($jobTitle) {
            $founder["jobTitle"] = $jobTitle;
        }
        
        $this->data["founder"] = $founder;
        return $this;
    }
    
    /**
     * Add employee
     */
    public function addEmployee(string $name, string $jobTitle = null): self
    {
        if (!isset($this->data["employee"])) {
            $this->data["employee"] = [];
        }
        
        $employee = [
            "@type" => "Person",
            "name" => $name
        ];
        
        if ($jobTitle) {
            $employee["jobTitle"] = $jobTitle;
        }
        
        $this->data["employee"][] = $employee;
        return $this;
    }
    
    /**
     * Set medical specialties (for hospitals/medical organizations)
     */
    public function setMedicalSpecialties(array $specialties): self
    {
        $this->data["medicalSpecialty"] = $specialties;
        return $this;
    }
    
    /**
     * Add available service
     */
    public function addService(string $name, string $description, string $type = "MedicalProcedure"): self
    {
        if (!isset($this->data["availableService"])) {
            $this->data["availableService"] = [];
        }
        
        $this->data["availableService"][] = [
            "@type" => $type,
            "name" => $name,
            "description" => $description
        ];
        
        return $this;
    }
    
    /**
     * Set payment methods accepted
     */
    public function setPaymentAccepted(array $paymentMethods): self
    {
        $this->data["paymentAccepted"] = $paymentMethods;
        return $this;
    }
    
    /**
     * Set currencies accepted
     */
    public function setCurrenciesAccepted(string $currency): self
    {
        $this->data["currenciesAccepted"] = $currency;
        return $this;
    }
    
    /**
     * Set price range (e.g., "$", "$$", "$$$", "$$$$")
     */
    public function setPriceRange(string $priceRange): self
    {
        $this->data["priceRange"] = $priceRange;
        return $this;
    }
    
    /**
     * Add credential/accreditation
     */
    public function addCredential(string $credentialCategory, string $recognizedBy): self
    {
        if (!isset($this->data["hasCredential"])) {
            $this->data["hasCredential"] = [];
        }
        
        $this->data["hasCredential"][] = [
            "@type" => "EducationalOccupationalCredential",
            "credentialCategory" => $credentialCategory,
            "recognizedBy" => [
                "@type" => "Organization",
                "name" => $recognizedBy
            ]
        ];
        
        return $this;
    }
    
    /**
     * Set social media profiles
     */
    public function setSocialMedia(array $socialUrls): self
    {
        $this->data["sameAs"] = $socialUrls;
        return $this;
    }
    
    /**
     * Set aggregate rating
     */
    public function setAggregateRating(
        string $ratingValue,
        string $reviewCount,
        string $bestRating = "5",
        string $worstRating = "1"
    ): self {
        $this->data["aggregateRating"] = [
            "@type" => "AggregateRating",
            "ratingValue" => $ratingValue,
            "reviewCount" => $reviewCount,
            "bestRating" => $bestRating,
            "worstRating" => $worstRating
        ];
        return $this;
    }
    
    /**
     * Add a review
     */
    public function addReview(
        string $authorName,
        string $ratingValue,
        string $reviewBody,
        string $bestRating = "5"
    ): self {
        if (!isset($this->data["review"])) {
            $this->data["review"] = [];
        }
        
        $this->data["review"][] = [
            "@type" => "Review",
            "author" => [
                "@type" => "Person",
                "name" => $authorName
            ],
            "reviewRating" => [
                "@type" => "Rating",
                "ratingValue" => $ratingValue,
                "bestRating" => $bestRating
            ],
            "reviewBody" => $reviewBody
        ];
        
        return $this;
    }
    
    /**
     * Set areas of knowledge/expertise
     */
    public function setKnowsAbout(array $knowledge): self
    {
        $this->data["knowsAbout"] = $knowledge;
        return $this;
    }
    
    /**
     * Set area served
     */
    public function setAreaServed(string $city, string $state, string $country): self
    {
        $this->data["areaServed"] = [
            [
                "@type" => "City",
                "name" => $city,
                "containedInPlace" => [
                    "@type" => "State",
                    "name" => $state,
                    "containedInPlace" => [
                        "@type" => "Country",
                        "name" => $country
                    ]
                ]
            ]
        ];
        return $this;
    }
    
    /**
     * Set slogan/tagline
     */
    public function setSlogan(string $slogan): self
    {
        $this->data["slogan"] = $slogan;
        return $this;
    }
    
    /**
     * Set founding date
     */
    public function setFoundingDate(string $foundingDate): self
    {
        $this->data["foundingDate"] = $foundingDate;
        return $this;
    }
    
    /**
     * Set number of employees
     */
    public function setNumberOfEmployees(string $numberOfEmployees): self
    {
        $this->data["numberOfEmployees"] = $numberOfEmployees;
        return $this;
    }
    
    /**
     * Set if accepting new patients/customers
     */
    public function setAcceptingNew(bool $accepting): self
    {
        $this->data["isAcceptingNewPatients"] = $accepting;
        return $this;
    }
    
    /**
     * Set smoking policy
     */
    public function setSmokingAllowed(bool $allowed): self
    {
        $this->data["smokingAllowed"] = $allowed;
        return $this;
    }
    
    /**
     * Add custom property
     */
    public function addCustomProperty(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Get the raw data array
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * Generate the JSON-LD script tag
     */
    public function generate(bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return '<script type="application/ld+json">' . 
               json_encode($this->data, $flags) . 
               '</script>';
    }
    
    /**
     * Output the JSON-LD script tag directly
     */
    public function output(bool $prettyPrint = true): void
    {
        echo $this->generate($prettyPrint);
    }
    
    /**
     * Convert to string (generates the script tag)
     */
    public function __toString(): string
    {
        return $this->generate();
    }
}
