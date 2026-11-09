<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $product = $options['data'] ?? null;
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
            ->add('image', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 255)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
