<?php

declare(strict_types=1);

namespace App\Renderer;

use Parser\Resolver;
use Handler\FieldFactory;
use FieldFactory\Form;

// Finální třída pro vykreslování formulářů
final class FormRenderer
{
	// Konstruktor přijímá instanci FieldFactory pro tvorbu polí formuláře
    public function __construct(private FieldFactory $fieldFactory) {}

	/**
     * Sestaví pole sekcí formuláře na základě ID generátoru.
     *
     * @param string $generatorId ID generátoru, podle kterého se načítá konfigurace
     * @return array Pole sekcí s HTML kódem
     */
    public function build(string $generatorId): array
    {
		// Získání dat generátoru podle ID
        $generator = Resolver::generatorResById($generatorId);
        $layoutRef = $generator['layout_ref'];

        $sections = [];

		// Procházení všech sekcí podle reference layoutu
        foreach (Resolver::sectionsResByRef($layoutRef) as $section) {

			// Procházení bloků v rámci sekce a vytvoření polí pomocí FieldFactory
            foreach (Resolver::sectionBlocksResById($layoutRef, $section['id']) as $block) {
				$this->fieldFactory->createFromBlock($block);
            }

			// Přidání sekce do výsledného pole včetně vygenerovaného HTML
            $sections[] = [
                'id'    => $section['id'],
                'label' => $section['label'] ?? null,
                'html'  => Form::render(),
            ];
        }

		 // Vrácení pole všech sekcí s HTML
        return $sections;
    }
}