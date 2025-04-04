<?php


namespace CodeQ\MonocleRenderer\DataSource;

use Neos\ContentRepository\Core\NodeType\NodeTypeNames;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\NodeType\NodeTypeCriteria;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Sitegeist\Monocle\Fusion\FusionService;
use Sitegeist\Monocle\Service\ConfigurationService;

class NodeTypeDataSource extends AbstractDataSource
{
    static protected $identifier = 'codeq-monoclerenderer-nodetype';

    #[Flow\Inject]
    protected FusionService $fusionService;

    #[Flow\Inject]
    protected ConfigurationService $configurationService;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SiteRepository $siteRepository;

    /**
     * @param  Node|null  $node
     * @param  array  $arguments
     * @return array
     * @throws \Neos\Neos\Domain\Exception
     */
    public function getData(Node $node = null, array $arguments = []): array
    {
        if ($node === null) {
            return [];
        }

        $contentSubgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
        $siteNode = $contentSubgraph->findClosestNode(
            $node->aggregateId,
            FindClosestNodeFilter::create(
                NodeTypeCriteria::createWithAllowedNodeTypeNames(
                    NodeTypeNames::fromStringArray(['Neos.Neos:Site']))
            )
        );

        if ($siteNode === null) {
            return [];
        }

        /** @var Site $site */
        $site = $this->siteRepository->findByNodeName($siteNode->name)->getFirst();
        $sitePackageKey = $site->getSiteResourcesPackageKey();

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
     * @param  string  $sitePackageKey
     * @return array
     * @throws \Neos\Neos\Domain\Exception
     */
    protected function getStyleguideObjects(string $sitePackageKey): array
    {
        $fusionAst = $this->fusionService->getFusionConfigurationForPackageKey($sitePackageKey);
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
                    static function ($prototypeName) use ($pattern, $alwaysShowPrototypes) {
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
     * @param  array  $prototypeStructures
     * @param  string  $prototypeName
     * @return array
     */
    protected function getStructureForPrototypeName(array $prototypeStructures, string $prototypeName): array
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
