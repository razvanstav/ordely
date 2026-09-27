<?php
declare(strict_types=1);
use Ordely\Adapters\Fake\{FakeCommerce,FakeCarrier,FakeInvoice};
use Ordely\Infrastructure\Configuration\Environment;
use Ordely\Integrations\Application\{ProviderDefinition,ProviderRegistry};
use Ordely\Integrations\Domain\{ProviderKind,Secrets};

$definitions=[new ProviderDefinition('shopify','Shopify · modul 07',ProviderKind::Commerce),new ProviderDefinition('oblio','Oblio · modul 09',ProviderKind::Invoice),new ProviderDefinition('sameday','Sameday · modul 10',ProviderKind::Carrier),new ProviderDefinition('fan','FAN Courier · modul 11',ProviderKind::Carrier)];
if(in_array(Environment::string('APP_ENV','dev'),['dev','test'],true)){
    $definitions[]=new ProviderDefinition('fake-commerce','Simulator magazin',ProviderKind::Commerce,['apiToken'],static fn(Secrets $secrets):FakeCommerce=>new FakeCommerce());
    $definitions[]=new ProviderDefinition('fake-carrier','Simulator curier',ProviderKind::Carrier,['apiToken'],static fn(Secrets $secrets):FakeCarrier=>new FakeCarrier());
    $definitions[]=new ProviderDefinition('fake-invoice','Simulator facturare',ProviderKind::Invoice,['apiToken'],static fn(Secrets $secrets):FakeInvoice=>new FakeInvoice());
}
return new ProviderRegistry(...$definitions);
