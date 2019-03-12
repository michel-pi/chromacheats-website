<?php

class AvatarGenerator
{
    public $BaseImage;
    public $FieldBoundary;

    public function __construct($image, $fieldBoundary)
    {
        $this->BaseImage = $image;
        $this->FieldBoundary = $fieldBoundary;
    }

    public function createTextRun($text, $font, $fontSize, $color)
    {
        return array(
            "text" => $text,
            "font" => array(
                "fontFile" => $font,
                "size" => $fontSize,
                "color" => $color,
                "anchor" => "top left",
                "xOffset" => $this->FieldBoundary["left"],
                "yOffset" => $this->FieldBoundary["top"]
            )
        );
    }

    public function generateAvatar($textRun, $outputFile)
    {
        if (empty($textRun)) return false;
        if (empty($outputFile)) return false;

        if (!is_array($textRun))
        {
            $textRun = array($textRun);
        }

        $sizes = $this->calculateStringSizes($textRun);

        $startOffsetX = 0;

        for ($i = 0; $i < count($sizes); $i++)
        {
            $startOffsetX += $sizes[$i]["width"];
        }

        $startOffsetX = $this->FieldBoundary["left"] + (($this->FieldBoundary["right"] - $this->FieldBoundary["left"]) - $startOffsetX) / 2;
        $startOffsetX -= count($textRun);

        $img = new \claviska\SimpleImage();
        $img = $img->fromFile($this->BaseImage);

        for ($i = 0; $i < count($textRun); $i++)
        {
            $size = $sizes[$i];

            $text = $textRun[$i]["text"];
            $font = $textRun[$i]["font"];

            $font["xOffset"] = $startOffsetX;
            $font["yOffset"] = $this->FieldBoundary["top"] + (($this->FieldBoundary["bottom"] - $this->FieldBoundary["top"]) - $font["size"]) / 2;

            $img = $img->text($text, $font);

            $startOffsetX += $size["width"] + 2;
        }

        $img->toFile($outputFile);
    }

    public function generateXenForoAvatar($textRun, $outputDir)
    {
        if (empty($textRun)) return false;
        if (empty($outputDir)) return false;

        if (!file_exists($outputDir))
        {
            mkdir($outputDir);
        }

        $small = $outputDir."/s.png";
        $medium = $outputDir."/m.png";
        $large = $outputDir."/l.png";

        $this->generateAvatar($textRun, $large);

        self::ScaleImage($large, $medium, 96, 96);
        self::ScaleImage($large, $small, 48, 48);
    }

    public function clipFontToBoundary($font, $text)
    {
        $maxWidth = $this->FieldBoundary["right"] - $this->FieldBoundary["left"];

        $img = new \claviska\SimpleImage();
        $img = $img->fromNew(
            $this->FieldBoundary["right"] - $this->FieldBoundary["left"],
            $this->FieldBoundary["bottom"] - $this->FieldBoundary["top"]
        );

        $count = 0;

        while (true)
        {
            $img->text($text, $font, $size);

            $width = $size["x2"] - $size["x1"];

            if ($width < $maxWidth)
            {
                if ($count == 0)
                {
                    return $font["size"];
                }
                else
                {
                    return $font["size"] - 1;
                }
                
            }
            else
            {
                $font["size"] -= 1;

                if ($font["size"] < 1)
                {
                    return 1;
                }
            }

            $count++;
        }
    }

    private function calculateStringSizes($textRun)
    {
        $sizes = array();

        $img = new \claviska\SimpleImage();
        $img = $img->fromNew(
            $this->FieldBoundary["right"] - $this->FieldBoundary["left"],
            $this->FieldBoundary["bottom"] - $this->FieldBoundary["top"]
        );
        //$img = $img->fromFile($this->BaseImage);

        for ($i = 0; $i < count($textRun); $i++)
        {
            $img = $img->text($textRun[$i]["text"], $textRun[$i]["font"], $size);

            $x = $size["x1"];
            $y = $size["y1"];
    
            $width = $size["x2"] - $x;
            $height = $size["y2"] - $y;
    
            $sizes[] = array("x" => $x, "y" => $y, "width" => $width, "height" => $height);
        }

        return $sizes;
    }

    public static function ScaleImage($input, $output, $width, $height)
    {
        $img = new \claviska\SimpleImage();
        $img = $img->fromFile($input)
            ->resize($width, $height)
            ->toFile($output);
    }

    public static function ScaleFont($text, $normalSize, $charLimit)
    {
        $len = strlen($text);

        if ($len < ($charLimit + 1))
        {
            return $normalSize;
        }
        else
        {
            $scaledFont = $normalSize - (($len - $charLimit) * 2);
            
            if ($scaledFont < 18)
            {
                return 18;
            }
            else
            {
                return $scaledFont;
            }
        }
    }
}

?>