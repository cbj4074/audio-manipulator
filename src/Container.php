<?php

namespace IndieHD\AudioManipulator;

use Dotenv\Dotenv;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use \getID3;

use IndieHD\AudioManipulator\Mp3\Mp3TagVerifier;
use IndieHD\AudioManipulator\Alac\AlacTagVerifier;
use IndieHD\AudioManipulator\Flac\FlacTagVerifier;
use IndieHD\AudioManipulator\Flac\FlacManipulatorCreator;
use IndieHD\AudioManipulator\Flac\FlacConverter;
use IndieHD\AudioManipulator\Flac\FlacTagger;
use IndieHD\AudioManipulator\Wav\WavManipulatorCreator;
use IndieHD\AudioManipulator\Wav\WavConverter;
use IndieHD\AudioManipulator\Mp3\Mp3ManipulatorCreator;
use IndieHD\AudioManipulator\Mp3\Mp3Converter;
use IndieHD\AudioManipulator\Mp3\Mp3Tagger;
use IndieHD\AudioManipulator\Validation\Validator;
use IndieHD\AudioManipulator\Processing\Process;
use IndieHD\AudioManipulator\MediaParsing\MediaParser;
use IndieHD\AudioManipulator\Alac\AlacManipulatorCreator;
use IndieHD\AudioManipulator\Alac\AlacTagger;
use IndieHD\AudioManipulator\CliCommand\AtomicParsleyCommand;
use IndieHD\AudioManipulator\CliCommand\SoxCommand;
use IndieHD\AudioManipulator\CliCommand\FfmpegCommand;
use IndieHD\AudioManipulator\CliCommand\MetaflacCommand;
use IndieHD\AudioManipulator\CliCommand\Mid3v2Command;

class Container
{
    public function __construct()
    {
        // Configuration.

        if (getenv('APP_ENV') === 'development') {
            Dotenv::create([__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR])->load();
        }

        // Container.

        $containerBuilder = new ContainerBuilder();

        // Validator.

        $containerBuilder->setParameter('validator.media_parser', new MediaParser);

        $containerBuilder->register('validator', Validator::class)
            ->addArgument('%validator.media_parser%');

        // Tag Verifier.

        $containerBuilder->register('alac.tag_verifier', AlacTagVerifier::class)
            ->addArgument('%alac.tag_verifier.getid3%');

        $containerBuilder->setParameter('alac.tag_verifier.getid3', new getID3());

        $containerBuilder->register('flac.tag_verifier', FlacTagVerifier::class)
            ->addArgument('%flac.tag_verifier.getid3%');

        $containerBuilder->setParameter('flac.tag_verifier.getid3', new getID3());

        $containerBuilder->register('mp3.tag_verifier', Mp3TagVerifier::class)
            ->addArgument('%mp3.tag_verifier.getid3%');

        $containerBuilder->setParameter('mp3.tag_verifier.getid3', new getID3());

        // ALAC Logger.

        $containerBuilder->register('logger.alac.tagger.handler', StreamHandler::class)
            ->addArgument(
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . getenv('ALAC_TAGGER_LOG')
            );

        $containerBuilder->register('logger.alac', Logger::class)
            ->addArgument('alac');

        // ALAC Tagger.

        $containerBuilder->register('alac_tagger', AlacTagger::class)
            ->addArgument('%alac_tagger.tag_verifier%')
            ->addArgument('%alac_tagger.process%')
            ->addArgument('%alac_tagger.logger%')
            ->addArgument('%alac_tagger.handler%')
            ->addArgument('%alac_tagger.cli_command%')
            ->addArgument('%alac_tagger.validator%');

        $containerBuilder->setParameter('alac_tagger.tag_verifier', new Reference('alac.tag_verifier'));
        $containerBuilder->setParameter('alac_tagger.process', new Process());
        $containerBuilder->setParameter('alac_tagger.logger', new Reference('logger.alac'));
        $containerBuilder->setParameter('alac_tagger.handler', new Reference('logger.alac.tagger.handler'));
        $containerBuilder->setParameter('alac_tagger.cli_command', new AtomicParsleyCommand());
        $containerBuilder->setParameter('alac_tagger.validator', $containerBuilder->get('validator'));

        // ALAC Manipulator.

        $containerBuilder->setParameter('alac_manipulator_creator.tagger', $containerBuilder->get('alac_tagger'));

        $containerBuilder
            ->register('alac_manipulator_creator', AlacManipulatorCreator::class)
            ->addArgument('%alac_manipulator_creator.tagger%');

        // FLAC Logger.

        $containerBuilder->register('logger.flac.converter.handler', StreamHandler::class)
            ->addArgument(
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . getenv('FLAC_CONVERTER_LOG')
            );

        $containerBuilder->register('logger.flac.tagger.handler', StreamHandler::class)
            ->addArgument(
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . getenv('FLAC_TAGGER_LOG')
            );

        $containerBuilder->register('logger.flac', Logger::class)
            ->addArgument('flac');

        // FLAC Converter.

        $containerBuilder->setParameter('flac_converter.validator', $containerBuilder->get('validator'));
        $containerBuilder->setParameter('flac_converter.process', new Process());
        $containerBuilder->setParameter('flac_converter.logger', new Reference('logger.flac'));
        $containerBuilder->setParameter('flac_converter.handler', new Reference('logger.flac.converter.handler'));
        $containerBuilder->setParameter('flac_converter.sox', new SoxCommand());
        $containerBuilder->setParameter('flac_converter.ffmpeg', new FfmpegCommand());

        $containerBuilder
            ->register('flac_converter', FlacConverter::class)
            ->addArgument('%flac_converter.validator%')
            ->addArgument('%flac_converter.process%')
            ->addArgument('%flac_converter.logger%')
            ->addArgument('%flac_converter.handler%')
            ->addArgument('%flac_converter.sox%')
            ->addArgument('%flac_converter.ffmpeg%');

        // FLAC Tagger.

        $containerBuilder->setParameter('flac_tagger.tag_verifier', new Reference('flac.tag_verifier'));
        $containerBuilder->setParameter('flac_tagger.process', new Process());
        $containerBuilder->setParameter('flac_tagger.logger', new Reference('logger.flac'));
        $containerBuilder->setParameter('flac_tagger.handler', new Reference('logger.flac.tagger.handler'));
        $containerBuilder->setParameter('flac_tagger.cli_command', new MetaflacCommand());
        $containerBuilder->setParameter('flac_tagger.validator', $containerBuilder->get('validator'));

        $containerBuilder->register('flac_tagger', FlacTagger::class)
            ->addArgument('%flac_tagger.tag_verifier%')
            ->addArgument('%flac_tagger.process%')
            ->addArgument('%flac_tagger.logger%')
            ->addArgument('%flac_tagger.handler%')
            ->addArgument('%flac_tagger.cli_command%')
            ->addArgument('%flac_tagger.validator%');

        // FLAC Manipulator.

        $containerBuilder->setParameter('flac_manipulator_creator.converter', $containerBuilder->get('flac_converter'));
        $containerBuilder->setParameter('flac_manipulator_creator.tagger', $containerBuilder->get('flac_tagger'));

        $containerBuilder
            ->register('flac_manipulator_creator', FlacManipulatorCreator::class)
            ->addArgument('%flac_manipulator_creator.converter%')
            ->addArgument('%flac_manipulator_creator.tagger%');

        // MP3 Logger.

        $containerBuilder->register('logger.mp3.converter.handler', StreamHandler::class)
            ->addArgument(
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . getenv('MP3_CONVERTER_LOG')
            );

        $containerBuilder->register('logger.mp3.tagger.handler', StreamHandler::class)
            ->addArgument(
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . getenv('MP3_TAGGER_LOG')
            );

        $containerBuilder->register('logger.mp3', Logger::class)
            ->addArgument('mp3');

        // MP3 Converter.

        $containerBuilder->setParameter('mp3_converter.validator', $containerBuilder->get('validator'));
        $containerBuilder->setParameter('mp3_converter.process', new Process());
        $containerBuilder->setParameter('mp3_converter.logger', new Reference('logger.mp3'));
        $containerBuilder->setParameter('mp3_converter.handler', new Reference('logger.mp3.converter.handler'));

        $containerBuilder
            ->register('mp3_converter', Mp3Converter::class)
            ->addArgument('%mp3_converter.validator%')
            ->addArgument('%mp3_converter.process%')
            ->addArgument('%mp3_converter.logger%')
            ->addArgument('%mp3_converter.handler%');

        // MP3 Tagger.

        $containerBuilder->setParameter('mp3_tagger.tag_verifier', new Reference('mp3.tag_verifier'));
        $containerBuilder->setParameter('mp3_tagger.process', new Process());
        $containerBuilder->setParameter('mp3_tagger.logger', new Reference('logger.mp3'));
        $containerBuilder->setParameter('mp3_tagger.handler', new Reference('logger.mp3.tagger.handler'));
        $containerBuilder->setParameter('mp3_tagger.cli_command', new Mid3v2Command());

        $containerBuilder->register('mp3_tagger', Mp3Tagger::class)
            ->addArgument('%mp3_tagger.tag_verifier%')
            ->addArgument('%mp3_tagger.process%')
            ->addArgument('%mp3_tagger.logger%')
            ->addArgument('%mp3_tagger.handler%')
            ->addArgument('%mp3_tagger.cli_command%');

        // MP3 Manipulator.

        $containerBuilder->setParameter('mp3_manipulator_creator.tagger', $containerBuilder->get('mp3_tagger'));

        $containerBuilder
            ->register('mp3_manipulator_creator', Mp3ManipulatorCreator::class)
            ->addArgument('%mp3_manipulator_creator.tagger%');

        // WAV Logger.

        $containerBuilder->register('logger.wav.converter.handler', StreamHandler::class)
            ->addArgument(
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . getenv('WAV_CONVERTER_LOG')
            );

        $containerBuilder->register('logger.wav', Logger::class)
            ->addArgument('wav');

        // WAV Converter.

        $containerBuilder->setParameter('wav_converter.validator', $containerBuilder->get('validator'));
        $containerBuilder->setParameter('wav_converter.process', new Process());
        $containerBuilder->setParameter('wav_converter.logger', new Reference('logger.wav'));
        $containerBuilder->setParameter('wav_converter.handler', new Reference('logger.wav.converter.handler'));

        $containerBuilder
            ->register('wav_converter', WavConverter::class)
            ->addArgument('%wav_converter.validator%')
            ->addArgument('%wav_converter.process%')
            ->addArgument('%wav_converter.logger%')
            ->addArgument('%wav_converter.handler%');

        // WAV Manipulator.

        $containerBuilder->setParameter('wav_manipulator_creator.converter', $containerBuilder->get('wav_converter'));

        $containerBuilder
            ->register('wav_manipulator_creator', WavManipulatorCreator::class)
            ->addArgument('%wav_manipulator_creator.converter%');

        //

        $this->builder = $containerBuilder;
    }
}
