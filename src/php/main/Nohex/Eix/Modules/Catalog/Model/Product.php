<?php

namespace Nohex\Eix\Modules\Catalog\Model;

use Nohex\Eix\Services\Data\Sources\MongoDB as DataSource;

/**
 * Representation of a product.
 */
class Product extends \Nohex\Eix\Services\Data\Entity
{
    const COLLECTION = 'products';

    protected $name;
    protected $description;
    protected $price;
    protected $weight;
    protected $presentation;
    protected $enabled = TRUE;
    protected $featured = FALSE;
    protected $groups = array();
    protected $pricePerKg;

    public function update(array $data, $isAtomic = TRUE)
    {
        parent::update($data, $isAtomic);

        // Set the groups.
        if (!empty($data['groups'])) {
            $this->groups = array();
            foreach ($data['groups'] as $key => $group) {
                if (!($group instanceof ProductGroup)) {
                    $productGroup = ProductGroups::getInstance()->getEntity($group['id']);
                    if (!$productGroup) {
                        $productGroup = new ProductGroup(array(
                            'id' => $group['id'],
                            'name' => $group['name'],
                        ));
                    }

                    $group = $productGroup;
                }
                // Keep the new entity.
                $this->groups[$group->id] = $group;
            }
        }

        // Invalidate calculated fields.
        $this->pricePerKg = null;
    }

    protected function getDefaultDataSource()
    {
        return DataSource::getInstance(static::COLLECTION);
    }

    protected function getFactory()
    {
        return Products::getInstance();
    }

    protected function getFields()
    {
        return array(
            'id',
            'name',
            'description',
            'enabled',
            'featured',
            'price',
            'weight',
            'presentation',
            'groups',
        );
    }

    protected function getFieldValidators()
    {
        return array(
            'id' => array('NonEmpty'),
            'name' => array('NonEmpty'),
            'description' => array('NonEmpty'),
            'price' => array('NonEmpty', 'Number'),
            'weight' => array('NonEmpty', 'Number'),
            'presentation' => array('NonEmpty'),
        );
    }

    /**
     * Make this product part of the specified group.
     * @param ProductGroup $group
     */
    public function addToGroup(ProductGroup $group)
    {
        if (!in_array($group, $this->groups)) {
            // Reference the group in the product.
            $this->groups[] = $group;
            // Reference the product in the group.
            $group->addProduct($this);
        }
    }

    /**
     * Remove this product from the specified group.
     * @param  ProductGroup      $group
     * @throws \RuntimeException if the product is only linked to one group.
     */
    public function removeFromGroup(ProductGroup $group)
    {
        // Remove the reference from this product.
        foreach ($this->groups as $index => $existingGroup) {
            if ($existingGroup == $group) {
                unset($this->groups[$index]);
                break;
            }
        }
        // Remove the product reference from the group.
        $group->removeProduct($this);
    }

    /**
     * Set this product's groups.
     * @param  array                     $groups
     * @throws \InvalidArgumentException if a group in the array is not valid.
     */
    public function setGroups(array $groups)
    {
        if (count($groups)) {
            foreach ($groups as $group) {
                if (!($group instanceof ProductGroup)) {
                    // One wrong group invalidates the groups array.
                    throw new \InvalidArgumentException('The product group is not valid.');
                }
            }
            $this->groups = $groups;
        }
    }

    /**
     * Allows the product to be displayed and used.
     */
    public function enable()
    {
        $this->enabled = TRUE;
    }

    /**
     * Prevents the product from being displayed or used.
     */
    public function disable()
    {
        $this->enabled = FALSE;
    }

    /**
     * Sets a product as featured.
     */
    public function promote()
    {
        $this->featured = TRUE;
    }

    /**
     * Marks the product as not featured.
     */
    public function demote()
    {
        $this->featured = FALSE;
    }

    /**
     * Returns a list of sizes a product image can be.
     */
    public static function getImageSizes()
    {
        return array(
            32,
            96,
            140,
        );
    }

    /**
     * Calculates this product's price per Kg.
     */
    public function getPricePerKg()
    {
        if (empty($this->pricePerKg)) {
            // If there is no weight or price, the value is unknown.
            if (($this->weight > 0) & ($this->price > 0)) {
                $this->pricePerKg = $this->price / $this->weight;
            } else {
                $this->pricePerKg = '—';
            }
        }

        return $this->pricePerKg;
    }
}
