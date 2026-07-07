<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSchoolWebsiteRequest extends FormRequest
{
    /**
     * Determine whether this request may proceed to validation.
     *
     * Authentication and school ownership are enforced by the route
     * middleware and controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for a complete website configuration.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contractVersion' => [
                'required',
                'integer',
                Rule::in([1]),
            ],

            'status' => [
                'required',
                'string',
                Rule::in([
                    'draft',
                    'published',
                    'unpublished',
                ]),
            ],

            'themeKey' => [
                'required',
                'string',
                Rule::in([
                    'kidza-home-2',
                ]),
            ],

            'branding' => [
                'required',
                'array:primaryColor,secondaryColor',
            ],
            'branding.primaryColor' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'branding.secondaryColor' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],

            'seo' => [
                'required',
                'array:title,description,imageUrl',
            ],
            'seo.title' => [
                'required',
                'string',
                'max:160',
            ],
            'seo.description' => [
                'required',
                'string',
                'max:320',
            ],
            'seo.imageUrl' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'header' => [
                'required',
                'array:welcomeText,utilityText,tagline',
            ],
            'header.welcomeText' => [
                'required',
                'string',
                'max:255',
            ],
            'header.utilityText' => [
                'required',
                'string',
                'max:255',
            ],
            'header.tagline' => [
                'required',
                'string',
                'max:255',
            ],

            'hero' => [
                'required',
                'array:eyebrow,title,description,imageUrl,primaryAction,secondaryAction,trustItems,infoCard',
            ],
            'hero.eyebrow' => [
                'required',
                'string',
                'max:255',
            ],
            'hero.title' => [
                'required',
                'string',
                'max:255',
            ],
            'hero.description' => [
                'required',
                'string',
                'max:2000',
            ],
            'hero.imageUrl' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'hero.primaryAction' => [
                'required',
                'array:label,href',
            ],
            'hero.primaryAction.label' => [
                'required',
                'string',
                'max:100',
            ],
            'hero.primaryAction.href' => [
                'required',
                'string',
                'max:2048',
            ],

            'hero.secondaryAction' => [
                'required',
                'array:label,href',
            ],
            'hero.secondaryAction.label' => [
                'required',
                'string',
                'max:100',
            ],
            'hero.secondaryAction.href' => [
                'required',
                'string',
                'max:2048',
            ],

            'hero.trustItems' => [
                'required',
                'array',
                'max:10',
            ],
            'hero.trustItems.*' => [
                'required',
                'string',
                'max:255',
            ],

            'hero.infoCard' => [
                'required',
                'array:label,title,description',
            ],
            'hero.infoCard.label' => [
                'required',
                'string',
                'max:100',
            ],
            'hero.infoCard.title' => [
                'required',
                'string',
                'max:255',
            ],
            'hero.infoCard.description' => [
                'required',
                'string',
                'max:1000',
            ],

            'highlights' => [
                'required',
                'array',
                'max:12',
            ],
            'highlights.*' => [
                'required',
                'array:id,title,description,iconUrl',
            ],
            'highlights.*.id' => [
                'required',
                'string',
                'max:100',
                'distinct',
            ],
            'highlights.*.title' => [
                'required',
                'string',
                'max:255',
            ],
            'highlights.*.description' => [
                'required',
                'string',
                'max:1000',
            ],
            'highlights.*.iconUrl' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'about' => [
                'required',
                'array:eyebrow,title,description,imageUrl,mission,vision',
            ],
            'about.eyebrow' => [
                'required',
                'string',
                'max:255',
            ],
            'about.title' => [
                'required',
                'string',
                'max:255',
            ],
            'about.description' => [
                'required',
                'string',
                'max:3000',
            ],
            'about.imageUrl' => [
                'nullable',
                'string',
                'max:2048',
            ],
            'about.mission' => [
                'required',
                'string',
                'max:3000',
            ],
            'about.vision' => [
                'required',
                'string',
                'max:3000',
            ],

            'programmes' => [
                'required',
                'array',
                'max:20',
            ],
            'programmes.*' => [
                'required',
                'array:id,name,summary,imageUrl',
            ],
            'programmes.*.id' => [
                'required',
                'string',
                'max:100',
                'distinct',
            ],
            'programmes.*.name' => [
                'required',
                'string',
                'max:255',
            ],
            'programmes.*.summary' => [
                'required',
                'string',
                'max:2000',
            ],
            'programmes.*.imageUrl' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'admissions' => [
                'required',
                'array:eyebrow,title,description,action',
            ],
            'admissions.eyebrow' => [
                'required',
                'string',
                'max:255',
            ],
            'admissions.title' => [
                'required',
                'string',
                'max:255',
            ],
            'admissions.description' => [
                'required',
                'string',
                'max:3000',
            ],
            'admissions.action' => [
                'required',
                'array:label,href',
            ],
            'admissions.action.label' => [
                'required',
                'string',
                'max:100',
            ],
            'admissions.action.href' => [
                'required',
                'string',
                'max:2048',
            ],

            'contact' => [
                'required',
                'array:address,phone,email,mapUrl',
            ],
            'contact.address' => [
                'required',
                'string',
                'max:1000',
            ],
            'contact.phone' => [
                'required',
                'string',
                'max:50',
            ],
            'contact.email' => [
                'required',
                'email',
                'max:255',
            ],
            'contact.mapUrl' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'socialLinks' => [
                'required',
                'array:facebook,instagram,linkedin,youtube,x',
            ],
            'socialLinks.facebook' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'socialLinks.instagram' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'socialLinks.linkedin' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'socialLinks.youtube' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'socialLinks.x' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'enabledSections' => [
                'required',
                'array:hero,highlights,about,programmes,admissions,contact',
            ],
            'enabledSections.hero' => [
                'required',
                'boolean',
            ],
            'enabledSections.highlights' => [
                'required',
                'boolean',
            ],
            'enabledSections.about' => [
                'required',
                'boolean',
            ],
            'enabledSections.programmes' => [
                'required',
                'boolean',
            ],
            'enabledSections.admissions' => [
                'required',
                'boolean',
            ],
            'enabledSections.contact' => [
                'required',
                'boolean',
            ],
        ];
    }
}
