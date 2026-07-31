<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Fixtures;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Repository\CardSetRepository;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Local development and demo data: two sets and twenty cards, including the
 * Mudkip 19/25 example used throughout the product specification. Automated
 * tests set up their own isolated data and do not depend on this fixture.
 *
 * Load with: php bin/console doctrine:fixtures:load --append
 * (--append avoids wiping accounts you already registered locally).
 */
final class CatalogFixtures extends Fixture
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardSetRepository $cardSets,
        private readonly CatalogCardRepository $catalogCards,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if ($this->cardSets->findByCode('mcd21') instanceof CardSet) {
            return;
        }

        $mcdonaldsSeries = new CardSeries("McDonald's Collections");
        $scarletVioletSeries = new CardSeries('Scarlet & Violet');
        $this->entityManager->persist($mcdonaldsSeries);
        $this->entityManager->persist($scarletVioletSeries);
        $this->entityManager->flush();

        $mcdonalds2021 = new CardSet(
            series: $mcdonaldsSeries,
            name: "McDonald's Collection 2021",
            code: 'mcd21',
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
            releaseDate: new DateTimeImmutable('2021-08-06'),
            logoUrl: $this->placeholderImage("McDonald's Collection 2021", 300, 120),
            symbolUrl: $this->placeholderImage('mcd21', 60, 60),
            description: 'Promotional 25-card set given out with Happy Meals to celebrate 25 years of the '
                .'trading card game. Every card is holo.',
        );
        $this->cardSets->save($mcdonalds2021);

        $scarletViolet151 = new CardSet(
            series: $scarletVioletSeries,
            name: 'Scarlet & Violet—151',
            code: 'sv3pt5',
            language: 'en',
            printedTotal: 165,
            total: 207,
            status: CardSetStatus::Active,
            releaseDate: new DateTimeImmutable('2023-09-22'),
            logoUrl: $this->placeholderImage('Scarlet & Violet 151', 300, 120),
            symbolUrl: $this->placeholderImage('sv3pt5', 60, 60),
            description: 'A set revisiting the original 151 collectible creatures, including modern illustration '
                .'rares.',
        );
        $this->cardSets->save($scarletViolet151);

        foreach ($this->mcdonalds2021Cards() as $definition) {
            $this->createCard($mcdonalds2021, $definition);
        }

        foreach ($this->scarletViolet151Cards() as $definition) {
            $this->createCard($scarletViolet151, $definition);
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     number: string,
     *     total: string,
     *     rarity: string,
     *     illustrator: string,
     *     primary: string,
     *     variants: list<VariantCode>
     * }>
     */
    private function mcdonalds2021Cards(): array
    {
        $card = static fn (string $name, string $number, string $illustrator): array => [
            'name' => $name,
            'number' => $number,
            'total' => '25',
            'rarity' => 'Promo',
            'illustrator' => $illustrator,
            'primary' => $name,
            'variants' => [VariantCode::Holo],
        ];

        return [
            $card('Bulbasaur', '1/25', 'Kagemaru Himeno'),
            $card('Charmander', '4/25', 'Kagemaru Himeno'),
            $card('Squirtle', '10/25', 'Kagemaru Himeno'),
            $card('Treecko', '13/25', 'Yuka Morii'),
            $card('Torchic', '16/25', 'Yuka Morii'),
            $card('Mudkip', '19/25', 'Yuka Morii'),
            $card('Eevee', '22/25', 'Kagemaru Himeno'),
            $card('Pikachu', '25/25', 'Atsuko Nishida'),
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     number: string,
     *     total: string,
     *     rarity: string,
     *     illustrator: string,
     *     primary: string,
     *     variants: list<VariantCode>
     * }>
     */
    private function scarletViolet151Cards(): array
    {
        return [
            $this->scarletVioletCard('Bulbasaur', '001/165', 'Common', 'Saya Tsuruta', [VariantCode::Normal]),
            $this->scarletVioletCard('Ivysaur', '002/165', 'Uncommon', 'Saya Tsuruta', [VariantCode::Normal]),
            $this->scarletVioletCard('Venusaur', '003/165', 'Rare', 'Saya Tsuruta', [VariantCode::Holo]),
            $this->scarletVioletCard(
                'Charmander',
                '004/165',
                'Common',
                'Futoshi Fujiwara',
                [VariantCode::Normal],
            ),
            $this->scarletVioletCard(
                'Charmeleon',
                '005/165',
                'Uncommon',
                'Futoshi Fujiwara',
                [VariantCode::Normal],
            ),
            $this->scarletVioletCard(
                'Charizard',
                '006/165',
                'Rare',
                'Futoshi Fujiwara',
                [VariantCode::Holo, VariantCode::SpecialIllustrationRare],
            ),
            $this->scarletVioletCard('Squirtle', '007/165', 'Common', 'Ryo Ueda', [VariantCode::Normal]),
            $this->scarletVioletCard('Blastoise', '009/165', 'Rare', 'Ryo Ueda', [VariantCode::Holo]),
            $this->scarletVioletCard(
                'Pikachu',
                '025/165',
                'Common',
                'Atsuko Nishida',
                [VariantCode::Normal, VariantCode::ReverseHolo],
            ),
            $this->scarletVioletCard('Eevee', '133/165', 'Common', 'Saya Tsuruta', [VariantCode::Normal]),
            $this->scarletVioletCard('Mewtwo', '150/165', 'Rare Holo', '5ban Graphics', [VariantCode::Holo]),
            $this->scarletVioletCard(
                'Mew',
                '151/165',
                'Illustration Rare',
                'Mina Nakai',
                [VariantCode::IllustrationRare],
            ),
        ];
    }

    /**
     * @param list<VariantCode> $variants
     *
     * @return array{
     *     name: string,
     *     number: string,
     *     total: string,
     *     rarity: string,
     *     illustrator: string,
     *     primary: string,
     *     variants: list<VariantCode>
     * }
     */
    private function scarletVioletCard(
        string $name,
        string $number,
        string $rarity,
        string $illustrator,
        array $variants,
    ): array {
        return [
            'name' => $name,
            'number' => $number,
            'total' => '165',
            'rarity' => $rarity,
            'illustrator' => $illustrator,
            'primary' => $name,
            'variants' => $variants,
        ];
    }

    /**
     * @param array{
     *     name: string,
     *     number: string,
     *     total: string,
     *     rarity: string,
     *     illustrator: string,
     *     primary: string,
     *     variants: list<VariantCode>
     * } $definition
     */
    private function createCard(CardSet $set, array $definition): void
    {
        $card = new CatalogCard(
            set: $set,
            name: $definition['name'],
            number: $definition['number'],
            rarity: $definition['rarity'],
            cardType: 'Pokémon',
            totalNumberInSet: $definition['total'],
            primaryCollectible: $definition['primary'],
            illustrator: $definition['illustrator'],
            imageUrl: $this->placeholderImage($definition['name'].' '.$definition['number'], 245, 342),
            thumbnailUrl: $this->placeholderImage($definition['name'].' '.$definition['number'], 122, 171),
            releaseDate: $set->getReleaseDate(),
        );

        foreach ($definition['variants'] as $variantCode) {
            $card->addVariant($variantCode);
        }

        $this->catalogCards->save($card);
    }

    private function placeholderImage(string $label, int $width, int $height): string
    {
        // Real card artwork may be protected; see product spec section 38. Development
        // and demo environments use placeholders until licensed image sources are wired up.
        return sprintf('https://placehold.co/%dx%d?text=%s', $width, $height, rawurlencode($label));
    }
}
