<?php

class Avatar
{
    public function execute(Request &$request, Response &$response)
    {
        $response->enable_caching();

        $path = FileSystem::JoinPaths(AppConfig::DIR_AVATAR, $userId);

            $fieldBoundary = array(
                "left" => 14,
                "top" => 135,
                "right" => 185,
                "bottom" => 166
            );

            $gen = new AvatarGenerator(AppConfig::IMG_NO_BG, $fieldBoundary);

            $fontSize = $this->calculateFontSize($gen, $username);

            $runs = array(
                $gen->createTextRun("<", AppConfig::FONT_SEGEOUI, $fontSize, "#03bc4e"),
                $gen->createTextRun($username, AppConfig::FONT_SEGEOUI, $fontSize, "#FFFFFF"),
                $gen->createTextRun("/>", AppConfig::FONT_SEGEOUI, $fontSize, "#03bc4e"),
            );

            $gen->generateXenForoAvatar($runs, $path);

            $this->writeCachedAvatar($path, $username);

            $this->showPicture($response, FileSystem::JoinPaths($path, $size));
    }

    private function calculateFontSize($gen, $username)
    {
        $font = array(
            "fontFile" => AppConfig::FONT_SEGEOUI,
            "size" => 26,
            "color" => "#000000",
            "anchor" => "top left",
            "xOffset" => 0,
            "yOffset" => 0
        );

        return $gen->clipFontToBoundary($font, "<".$username."/>");
    }

    private function writeCachedAvatar($path, $username)
    {
        $handle = fopen($path."/cache.txt", "w");

        fwrite($handle, $username);

        fclose($handle);
    }

    private function getCachedAvatar($userId, $username)
    {
        $path = FileSystem::JoinPaths(AppConfig::DIR_AVATAR, $userId);

        if (file_exists($path))
        {
            $cacheFile = FileSystem::JoinPaths($path, "/cache.txt");

            if (file_exists($cacheFile))
            {
                $content = file_get_contents($cacheFile);

                if ($content == $username)
                {
                    return $path;
                }
                else
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
        }
        else
        {
            mkdir($path);

            return false;
        }
    }
    
    private function showPicture(Response &$response, $path)
    {
        $response->add_header("content-type: ".mime_content_type($path));
        $response->add_header("content-length: ".filesize($path));

        $response->add_header("accept-ranges: bytes");

        $response->Data = file_get_contents($path);
    }
}

?>