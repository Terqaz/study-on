<?php

namespace App\Dto;

class CourseDto
{
    private string $code;

    private string $name;

    private string $type;

    private float $price = 0.0;

    /**
     * @param string $code
     * @return CourseDto
     */
    public function setCode(string $code): CourseDto
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @param string $name
     * @return CourseDto
     */
    public function setName(string $name): CourseDto
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $type
     * @return CourseDto
     */
    public function setType(string $type): CourseDto
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param float $price
     * @return CourseDto
     */
    public function setPrice(float $price): CourseDto
    {
        $this->price = $price;
        return $this;
    }
}
