# Why this test set is invented

`fiche-example.csv` holds **no data of France compétences**, and that is deliberate.

The file carries the code `RS00000`, a number that exists in no register, and labels written
for the occasion. A file downloaded from data.gouv.fr would have been more realistic, and it
would have brought data under the **Licence Ouverte 2.0 into a GPLv3 tree**, which is exactly
the open point PO-2 that this project has not yet had settled.

As long as PO-2 is not examined, the rule is simple: **no real record in the repository.**
Real records are loaded at run time, through the import screen, and live in the database,
never in the code.
