<?php

use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

it('defines the school website relationships correctly', function () {
    $school = new School;
    $website = new SchoolWebsite;

    $schoolWebsiteRelationship = $school->website();
    $websiteSchoolRelationship = $website->school();

    expect($schoolWebsiteRelationship)
        ->toBeInstanceOf(HasOne::class)
        ->and($schoolWebsiteRelationship->getRelated())
        ->toBeInstanceOf(SchoolWebsite::class)
        ->and($schoolWebsiteRelationship->getForeignKeyName())
        ->toBe('school_id')
        ->and($websiteSchoolRelationship)
        ->toBeInstanceOf(BelongsTo::class)
        ->and($websiteSchoolRelationship->getRelated())
        ->toBeInstanceOf(School::class)
        ->and($websiteSchoolRelationship->getForeignKeyName())
        ->toBe('school_id');
});
