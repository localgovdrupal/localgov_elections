# LocalGov Elections Reporting ONS Wards 2023 (Boundary Source Provider)

A key part of the module is the idea of boundary source providers. Given that there are many different ways to classify
election areas (wards, parishes, constituencies) and also change over time. This means we can't provide a one size fits
all solution.

See the [documentation](../../docs/index.md) for details on how to use this.

## Data Sources

The data for ONS Wards 2023 plugin is provided by the Office for National Statistics (ONS). The ONS provides various
types of geo-data through their Open Geography Portal https://geoportal.statistics.gov.uk/. The dataset we use for the
plugin is
titled "[Wards (May 2023) Boundaries UK BFE](https://geoportal.statistics.gov.uk/datasets/ons::wards-may-2023-boundaries-uk-bfe/explore)".
This dataset provides boundary data for UK Wards.

We also link to a different dataset in the plugin form which is used to help the user find the correct Local Authority
District Code for when specifying their electoral area. This dataset is
titled "[Local Authority Districts (April 2023) Names and Codes in the United Kingdom](https://geoportal.statistics.gov.uk/datasets/ons::local-authority-districts-april-2023-names-and-codes-in-the-united-kingdom/explore?showTable=true)".

## Boundary Fetching Process

The boundary fetching process used by the ONS Wards 2023 plugin is fairly simple and can be described in a few steps:

1. Get the Local Authority District Code on the plugin configuration form. This limits the fetched boundaries to a
   specific area. The plugin form validates the district code is valid, so you can't just enter anything here.
2. The user press saves and a boundary source entity is saved with a reference to the plugin.
3. The user is able to select the source entity from the boundary fetch form which is linked to from the election node.
4. The plugin presents a subform which shows all the individual areas in the electoral district (specified by the
   district code).
5. The user selects the areas they want and then clicks submit. The areas are then downloaded to area vote nodes with
   the boundary data attached.
