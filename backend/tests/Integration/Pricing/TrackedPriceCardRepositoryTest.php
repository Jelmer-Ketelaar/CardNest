<?php

declare(strict_types=1);

namespace App\Tests\Integration\Pricing;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\Collection as CardCollection;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Pricing\Domain\Repository\TrackedPriceCardRepository;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;
use App\Wishlist\Domain\Entity\WishlistPriority;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class TrackedPriceCardRepositoryTest extends KernelTestCase
{
    public function testItTracksOpenWishlistAndActiveCollectionCardsOnly(): void
    {
        // Arrange
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $container->get(TrackedPriceCardRepository::class);
        self::assertInstanceOf(TrackedPriceCardRepository::class, $repository);
        $suffix = Uuid::v7()->toRfc4122();
        $user = new User('pricing-'.$suffix.'@example.test', 'hash');
        $series = new CardSeries('Pricing candidates '.$suffix);
        $set = new CardSet(
            $series,
            'Candidate set',
            'price-'.$suffix,
            'en',
            4,
            4,
            CardSetStatus::Active,
        );
        $openWishlistCard = new CatalogCard($set, 'Open wishlist card', '1/4', 'Common', 'Pokemon');
        $completedWishlistCard = new CatalogCard($set, 'Completed wishlist card', '2/4', 'Common', 'Pokemon');
        $activeCollectionCard = new CatalogCard($set, 'Active collection card', '3/4', 'Common', 'Pokemon');
        $archivedCollectionCard = new CatalogCard($set, 'Archived collection card', '4/4', 'Common', 'Pokemon');
        $activeVariant = $activeCollectionCard->addVariant(VariantCode::Normal);
        $archivedVariant = $archivedCollectionCard->addVariant(VariantCode::Normal);
        $wishlist = new Wishlist($user, 'Price candidates');
        $openItem = new WishlistItem(
            $wishlist,
            $openWishlistCard,
            null,
            null,
            null,
            null,
            1,
            WishlistPriority::Normal,
        );
        $completedItem = new WishlistItem(
            $wishlist,
            $completedWishlistCard,
            null,
            null,
            null,
            null,
            1,
            WishlistPriority::Normal,
        );
        $completedItem->fulfill();
        $wishlist->addItem($openItem);
        $wishlist->addItem($completedItem);
        $collection = new CardCollection($user);
        $activeItem = new CollectionItem(
            $collection,
            $activeCollectionCard,
            $activeVariant,
            CardLanguage::English,
            CardCondition::NearMint,
        );
        $archivedItem = new CollectionItem(
            $collection,
            $archivedCollectionCard,
            $archivedVariant,
            CardLanguage::English,
            CardCondition::NearMint,
        );
        $archivedItem->archive();
        foreach ([
            $user,
            $series,
            $set,
            $openWishlistCard,
            $completedWishlistCard,
            $activeCollectionCard,
            $archivedCollectionCard,
            $wishlist,
            $collection,
            $activeItem,
            $archivedItem,
        ] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        // Act
        $trackedIds = iterator_to_array($repository->cardIds(), false);

        // Assert
        self::assertContains($openWishlistCard->getId()->toRfc4122(), $trackedIds);
        self::assertContains($activeCollectionCard->getId()->toRfc4122(), $trackedIds);
        self::assertNotContains($completedWishlistCard->getId()->toRfc4122(), $trackedIds);
        self::assertNotContains($archivedCollectionCard->getId()->toRfc4122(), $trackedIds);
    }
}
