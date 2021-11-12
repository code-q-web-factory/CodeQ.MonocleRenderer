<?php


namespace CodeQ\MonocleRenderer\DataSource;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Sitegeist\Monocle\Fusion\FusionService;
use Sitegeist\Monocle\Service\ConfigurationService;

class NodeTypeDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    static protected $identifier = 'codeq-monoclerenderer-nodetype';

    /**
     * @Flow\Inject
     * @var FusionService
     */
    protected $fusionService;

    /**
     * @Flow\Inject
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @param NodeInterface|null $node
     * @param array              $arguments
     *
     * @return array
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $sitePackageKey = $node->getContext()->getCurrentSite()->getSiteResourcesPackageKey();

        $data = [];
        foreach($this->getStyleguideObjects($sitePackageKey) as $prototypeName => $styleguideObject) {
            $data[] = array(
                'label' => $styleguideObject['title'],
                'value' => $prototypeName,
                'icon' => $styleguideObject['structure']['icon']
            );
        }

        return $data;
    }

    /**
     * @param $sitePackageKey
     * @param $styleguideObject
     * @return array
     * @throws \Neos\Neos\Domain\Exception
     */
    protected function getStyleguideObjects($sitePackageKey): array
    {
        $fusionAst = $this->fusionService->getMergedFusionObjectTreeForSitePackage($sitePackageKey);
        $styleguideObjects = $this->fusionService->getStyleguideObjectsFromFusionAst($fusionAst);
        $prototypeStructures = $this->configurationService->getSiteConfiguration($sitePackageKey, 'ui.structure');

        foreach ($styleguideObjects as $prototypeName => &$styleguideObject) {
            $styleguideObject['structure'] = $this->getStructureForPrototypeName($prototypeStructures, $prototypeName);
        }

        $hiddenPrototypeNamePatterns = $this->configurationService->getSiteConfiguration($sitePackageKey, 'hiddenPrototypeNamePatterns');
        if (is_array($hiddenPrototypeNamePatterns)) {
            $alwaysShowPrototypes = $this->configurationService->getSiteConfiguration($sitePackageKey, 'alwaysShowPrototypes');
            foreach ($hiddenPrototypeNamePatterns as $pattern) {
                $styleguideObjects = array_filter(
                    $styleguideObjects,
                    function ($prototypeName) use ($pattern, $alwaysShowPrototypes) {
                        if (in_array($prototypeName, $alwaysShowPrototypes, true)) {
                            return true;
                        }
                        return fnmatch($pattern, $prototypeName) === false;
                    },
                    ARRAY_FILTER_USE_KEY
                );
            }
        }
        return $styleguideObjects;
    }

    /**
     * Find the matching structure for a prototype
     *
     * @param $prototypeStructures
     * @param $prototypeName
     * @return array
     */
    protected function getStructureForPrototypeName($prototypeStructures, $prototypeName)
    {
        foreach ($prototypeStructures as $structure) {
            if (preg_match(sprintf('!%s!', $structure['match']), $prototypeName)) {
                return $structure;
            }
        }

        return [
            'label' => 'Other',
            'icon' => 'icon-question',
            'color' => 'white'
        ];
    }
}
