#!/usr/bin/env python

import argparse
import os
import re
import subprocess

import numpy as np
import pandas as pd

parser = argparse.ArgumentParser(
    description="build model complexes and score using Rosetta's fixbb app"
)
parser.add_argument(
    "-f",
    help="ATLAS Mutants tab-delimited file (ex. Mutants_052915.tsv)",
    type=str,
    dest="f",
    required=False,
    default="../www/tables/ATLAS.tsv"
)
parser.add_argument(
    "-w",
    help="Weights file for scoring with Rosetta (ex. weights_1.wts)",
    type=str,
    dest="w",
    required=False,
    default="./weights_1.wts"
)
parser.add_argument(
    "-r",
    help="path to Rosetta3 (ex. /home/borrmant/Research/TCR/rosetta/rosetta-3.5/)",
    type=str,
    dest="ros_path",
    required=False,
    default="../rosetta/main"
)
parser.add_argument(
    "-s",
    help="path to Brian Pierce's TCR-pMHC structure database (ex. /home/borrmant/Research/TCR/tcr_structure_database/all/)",
    type=str,
    dest="struct_path",
    required=False,
    default="../www/structures"
)
args = parser.parse_args()

designed_path = os.path.join(args.struct_path, "designed_pdb")
if not os.path.exists(designed_path):
    os.makedirs(designed_path)


def score(pdb, weights, label):
    """
    Score PDB complex using Rosetta3 scoring function
    """
    score_cmd = [
        os.path.join(args.ros_path, "source/bin/score.static.linuxgccrelease"),
        "-database",
        os.path.join(args.ros_path, "database"),
        "-s",
        pdb,
        "-out:file:scorefile",
        label + "_score.sc",
        "-extrachi_cutoff",
        "1",
        "-ex1",
        "-ex2",
        "-ex3",
        "-score:weights",
        weights,
    ]
    process = subprocess.Popen(score_cmd)
    process.wait()


def isolate(pdb, component):
    """
    Isolate chain components from PDB structure
    """
    if component == "TCR":
        IN = open(pdb, "r")
        OUT = open(component + ".pdb", "w")
        for line in IN:
            row = line.split()
            if len(row) > 0 and row[0] == "ATOM":
                if row[4] == "D" or row[4] == "E":
                    OUT.write(line)
        IN.close()
        OUT.close()
    elif component == "pMHC":
        IN = open(pdb, "r")
        OUT = open(component + ".pdb", "w")
        for line in IN:
            row = line.split()
            if len(row) > 0 and row[0] == "ATOM":
                if row[4] == "A" or row[4] == "B" or row[4] == "C":
                    OUT.write(line)
        IN.close()
        OUT.close()
    else:
        print("ERORR: unidentified component name")
        quit()


def dG_bind(COM_sc, TCR_sc, pMHC_sc):
    """
    Calculate dG_bind scores = COM_score - (TCR_score + pMHC_score)
    """
    COM = open(COM_sc, "r")
    TCR = open(TCR_sc, "r")
    pMHC = open(pMHC_sc, "r")

    header = COM.readline().split()[2:9]
    TCR.readline()
    pMHC.readline()
    COM_scores = np.array(list(map(float, COM.readline().split()[2:9])))
    TCR_scores = np.array(list(map(float, TCR.readline().split()[2:9])))
    pMHC_scores = np.array(list(map(float, pMHC.readline().split()[2:9])))
    dG_scores = COM_scores - (TCR_scores + pMHC_scores)
    dG_hash = {}
    for i, energy in enumerate(header):
        dG_hash[energy] = dG_scores[i]
    COM.close()
    TCR.close()
    pMHC.close()
    return dG_hash


def make_resfile(MHC_mut, MHC_mut_chain, TCR_mut, TCR_mut_chain, PEP_mut):
    """
    Make resfile specifying mutations to design using fixbb app
    NOTE:
    Follow Brian Pierce's naming convention in the tcr_structure_database, namely:
    MHC chain A -> chain A
    MHC chain B -> chain B
    TCR chain A -> chain D
    TCR chain B -> chain E
    peptide -> chain C
    """

    tcr_chain_map = {"A": "D", "B": "E"}
    RF = open("resfile", "w")
    RF.write("NATRO\nstart\n")
    if MHC_mut != "WT":
        if pd.isnull(MHC_mut_chain):
            print("ERROR: MHC_mut_chain missing")
            quit()
        muts = re.findall("[A-Z]\d+[A-Z]", MHC_mut)
        chains = re.findall("[A-Z]", MHC_mut_chain)
        if len(muts) != len(chains):
            print("ERROR: not a chain for every mut")
            quit()
        for i in range(len(muts)):
            search_obj = re.search("[A-Z](\d+)([A-Z])", muts[i])
            res_num = search_obj.group(1)
            mut_aa = search_obj.group(2)
            RF.write(res_num + " " + chains[i] + " PIKAA " + mut_aa + "\n")
    if TCR_mut != "WT":
        if pd.isnull(TCR_mut_chain):
            print("ERROR: TCR_mut_chain missing")
            quit()
        muts = re.findall("[A-Z]\d+[A-Z]", TCR_mut)
        chains = re.findall("[A-Z]", TCR_mut_chain)
        if len(muts) != len(chains):
            print("ERROR: not a chain for every mut")
            quit()
        for i in range(len(muts)):
            search_obj = re.search("[A-Z](\d+)([A-Z])", muts[i])
            res_num = search_obj.group(1)
            mut_aa = search_obj.group(2)
            RF.write(
                res_num + " " + tcr_chain_map[chains[i]] + " PIKAA " + mut_aa + "\n"
            )
    if PEP_mut != "WT":
        muts = re.findall("[A-Z]\d+[A-Z]", PEP_mut)
        for mut in muts:
            search_obj = re.search("[A-Z](\d+)([A-Z])", mut)
            res_num = search_obj.group(1)
            mut_aa = search_obj.group(2)
            RF.write(res_num + " C PIKAA " + mut_aa + "\n")
    RF.close()


def fixbb(pdb, resfile, label):
    """
    Design mutations using Rosetta's fixed backbone application
    """
    fixbb_cmd = [
        os.path.join(args.ros_path, "source/bin/fixbb.static.linuxgccrelease"),
        "-database",
        os.path.join(args.ros_path, "database"),
        "-s",
        pdb,
        "-resfile",
        resfile,
        "-suffix",
        label,
        "-extrachi_cutoff",
        "1",
        "-ex1",
        "-ex2",
        "-ex3",
        "-overwrite",
        "-out:path:pdb",
        designed_path,
    ]
    process = subprocess.Popen(fixbb_cmd)
    process.wait()


def main():
    # Read Mutants table into dataframe
    df = pd.read_csv(args.f, sep="\t")
    # Add energy term columns
    WF = open(args.w, "r")
    for line in WF:
        energy = line.split()[0]
        df[energy] = np.nan

    for i, row in df.iterrows():
        # Check if we need to model entry
        if pd.notnull(row["true_PDB"]):
            true_pdb = str(row["true_PDB"])
            true_pdb_path = os.path.join(args.struct_path, "true_pdb", true_pdb + ".pdb")
            # Score TCR-pMHC
            score(true_pdb_path, args.w, "COM")
            # Score TCR
            isolate(true_pdb_path, "TCR")
            score("TCR.pdb", args.w, "TCR")
            # Score pMHC
            isolate(true_pdb_path, "pMHC")
            score("pMHC.pdb", args.w, "pMHC")
            # Calculate dG bind
            dG_scores = dG_bind("COM_score.sc", "TCR_score.sc", "pMHC_score.sc")
            # Append to dataframe
            for energy in dG_scores:
                df.loc[i, energy] = dG_scores[energy]
            # Remove temporary files
            os.remove("COM_score.sc")
            os.remove("TCR_score.sc")
            os.remove("pMHC_score.sc")
            os.remove("TCR.pdb")
            os.remove("pMHC.pdb")

        # Design model TCR-pMHC
        elif pd.isnull(row["true_PDB"]):
            template_pdb = str(row["template_PDB"])
            # Get mutations that need to be designed
            MHC_mut = df.loc[i, "MHC_mut"]
            MHC_mut_chain = df.loc[i, "MHC_mut_chain"]
            TCR_mut = df.loc[i, "TCR_mut"]
            TCR_mut_chain = df.loc[i, "TCR_mut_chain"]
            PEP_mut = df.loc[i, "PEP_mut"]
            # Make resfile for fixbb app
            make_resfile(MHC_mut, MHC_mut_chain, TCR_mut, TCR_mut_chain, PEP_mut)
            # Make label for structure
            label = "_".join(
                map(str, [MHC_mut, MHC_mut_chain, TCR_mut, TCR_mut_chain, PEP_mut])
            )
            label = "_" + re.sub("\s+", "", label)
            # Design mutations by fixbb app and save structure
            fixbb(
                os.path.join(args.struct_path, "true_pdb", template_pdb + ".pdb"), "resfile", label
            )
            # Remove temp files
            os.remove("resfile")
            os.remove("score" + label + ".sc")
            # Score TCR-pMHC
            old_design_template_pdb_path = os.path.join(designed_path, template_pdb + label + "_0001.pdb")
            design_template_pdb_path = os.path.join(
               designed_path, template_pdb +  re.sub("\|", ".", label) + ".pdb"
            )
            os.rename(old_design_template_pdb_path,design_template_pdb_path)
            score(
                design_template_pdb_path,
                args.w,
                "COM",
            )
            # Score TCR
            isolate(design_template_pdb_path, "TCR")
            score("TCR.pdb", args.w, "TCR")
            # Score pMHC
            isolate(design_template_pdb_path, "pMHC")
            score("pMHC.pdb", args.w, "pMHC")
            # Calculate dG bind
            dG_scores = dG_bind("COM_score.sc", "TCR_score.sc", "pMHC_score.sc")
            # Append to dataframe
            for energy in dG_scores:
                df.loc[i, energy] = dG_scores[energy]
            # Remove temporary files
            os.remove("COM_score.sc")
            os.remove("TCR_score.sc")
            os.remove("pMHC_score.sc")
            os.remove("TCR.pdb")
            os.remove("pMHC.pdb")

    # Write to file
    df.to_csv("test_table.csv", sep="\t", index=False)


if __name__ == "__main__":
    main()
