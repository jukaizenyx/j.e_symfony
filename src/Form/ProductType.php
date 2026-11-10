<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\File;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $product = $options['data'] ?? null;
        $isNewProduct = !($product instanceof Product) || $product->getId() === null;
        $storedSizes = $product instanceof Product ? json_decode($product->getSizes(), true) : [];
        $sizeRows = [];
        if (is_array($storedSizes) && !array_is_list($storedSizes)) {
            foreach ($storedSizes as $size => $price) {
                $sizeRows[] = ['size' => (string) $size, 'price' => $price];
            }
        }

        $builder
            ->add('name', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 100)]])
            ->add('description', TextareaType::class, ['constraints' => [new NotBlank()]])
            ->add('sizes', CollectionType::class, [
                'entry_type' => ProductSizePriceType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'mapped' => false,
                'data' => $sizeRows !== [] ? $sizeRows : [['size' => '', 'price' => '']],
                'prototype_name' => '__size__',
            ])
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => $isNewProduct,
                'constraints' => array_filter([
                    $isNewProduct ? new NotBlank(message: 'Choose an image for this product.') : null,
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/bmp', 'image/x-ms-bmp'],
                        mimeTypesMessage: 'Upload a JPEG, PNG, WebP, GIF, AVIF, or BMP image.',
                    ),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
